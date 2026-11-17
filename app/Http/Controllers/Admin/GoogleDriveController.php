<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\User;
use App\Services\DriveSync;
use App\Services\GoogleDrive;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveController extends Controller
{
    public function show(GoogleDrive $drive)
    {
        $connection = $drive->connected() ? $drive->connection() : [];

        return view('admin.drive', [
            'configured' => $drive->configured(),
            'connected' => $drive->connected(),
            'connection' => $connection,
            'connectedBy' => isset($connection['connected_by']) ? User::find($connection['connected_by']) : null,
            'redirectUri' => $drive->redirectUri(),
            'counts' => [
                'google' => Evidence::where('storage', 'google')->count(),
                'local' => Evidence::where('storage', 'local')->count(),
                'backedUp' => Evidence::where('storage', 'google')->where('path', 'not like', 'gdrive:%')->count(),
            ],
        ]);
    }

    public function connect(Request $request, GoogleDrive $drive)
    {
        abort_unless($drive->configured(), 409, 'Google Drive is not configured on the server.');

        $state = Str::random(40);
        $request->session()->put('google_drive_state', $state);

        return redirect()->away($drive->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDrive $drive)
    {
        $expected = $request->session()->pull('google_drive_state');

        if ($request->filled('error')) {
            return redirect()->route('admin.drive.show')->withErrors(['drive' => 'Google Drive was not connected: '.$request->input('error').'.']);
        }
        if (! $expected || ! hash_equals($expected, (string) $request->input('state')) || ! $request->filled('code')) {
            return redirect()->route('admin.drive.show')->withErrors(['drive' => 'The Google sign-in could not be verified. Please try again.']);
        }

        try {
            $connection = $drive->connect($request->input('code'), $request->user()->id);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.drive.show')->withErrors(['drive' => 'Connecting Google Drive failed: '.$e->getMessage()]);
        }

        return redirect()->route('admin.drive.show')
            ->with('status', 'Google Drive connected ('.($connection['email'] ?? 'unknown account').'). New evidence files will be stored there.');
    }

    /**
     * One batch of the server → Drive sync. The page calls this repeatedly (JSON) and shows
     * progress; without JavaScript it runs one batch and comes back to the page.
     */
    public function sync(Request $request, DriveSync $sync, GoogleDrive $drive)
    {
        if (! $drive->connected()) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Google Drive is not connected.'], 409)
                : redirect()->route('admin.drive.show')->withErrors(['drive' => 'Connect Google Drive before syncing.']);
        }

        $skip = array_map('intval', (array) $request->input('skip', []));
        $result = $sync->batch(skip: $skip);

        if ($request->wantsJson()) {
            return response()->json($result + ['total_in_drive' => Evidence::where('storage', 'google')->count()]);
        }

        $message = $result['synced'].' file(s) copied to Google Drive.';
        $message .= $result['remaining'] ? ' '.$result['remaining'].' still to go: press Sync again.' : ' Every server file is now in Drive.';
        if ($result['failed']) {
            $message .= ' '.count($result['failed']).' could not be copied: '.collect($result['failed'])->map(fn ($f) => $f['name'].' ('.$f['reason'].')')->join(', ').'.';
        }

        return redirect()->route('admin.drive.show')->with('status', $message);
    }

    public function disconnect(GoogleDrive $drive)
    {
        $drive->disconnect();

        return redirect()->route('admin.drive.show')
            ->with('status', 'Google Drive disconnected. New evidence files will be stored on the server. Files already in Drive stay there but can\'t be downloaded until Drive is reconnected to the same account.');
    }
}
