<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Evidence;
use App\Services\EvidenceStore;
use App\Services\GoogleDrive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EvidenceController extends Controller
{
    public function store(Request $request, Activity $activity, EvidenceStore $store)
    {
        abort_unless($activity->deliverable->project->accessibleBy($request->user()), 403);
        abort_unless($request->user()->canUpdateWbs(), 403, 'Your account role cannot update the WBS.');

        if (! $activity->isEditable()) {
            return $this->backToActivity($activity)->withErrors(['files' => $this->lockedMessage($activity)], 'evidence'.$activity->id);
        }

        $request->validateWithBag('evidence'.$activity->id, [
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => EvidenceStore::FILE_RULES,
        ], EvidenceStore::MESSAGES);

        $result = $store->storeAll($request->file('files'), $activity, $request->user());

        $message = $result['count'].' evidence file(s) uploaded for "'.$activity->name.'"'.($result['toDrive'] === $result['count'] ? ' to Google Drive.' : '.');
        if ($result['fellBack']) {
            $message .= ' '.$result['fellBack'].' could not be sent to Google Drive and were stored on the server instead.';
        }

        return $this->backToActivity($activity)->with('status', $message);
    }

    public function download(Request $request, Evidence $evidence, GoogleDrive $drive)
    {
        abort_unless($evidence->activity->deliverable->project->accessibleBy($request->user()), 403);

        $serverCopy = $evidence->hasServerCopy() && Storage::disk('local')->exists($evidence->path);

        if ($evidence->onDrive() && $drive->connected()) {
            try {
                return $drive->download($evidence->drive_file_id, $evidence->original_name, $evidence->mime);
            } catch (Throwable $e) {
                report($e);
                abort_unless($serverCopy, 502, 'The file could not be fetched from Google Drive.');
                // Synced files keep a server copy: serve that instead.
            }
        }

        if ($evidence->onDrive() && ! $serverCopy) {
            abort(503, 'This file is stored in Google Drive, which is not connected right now. Ask an admin to reconnect it.');
        }

        abort_unless($serverCopy, 404, 'The file was not found on the server.');

        return Storage::disk('local')->download($evidence->path, $evidence->original_name);
    }

    public function destroy(Request $request, Evidence $evidence, GoogleDrive $drive)
    {
        $activity = $evidence->activity;
        abort_unless($activity->deliverable->project->accessibleBy($request->user()), 403);
        abort_unless($request->user()->canUpdateWbs(), 403, 'Your account role cannot update the WBS.');

        if (! $activity->isEditable()) {
            return $this->backToActivity($activity)->withErrors(['file' => $this->lockedMessage($activity)], 'evidence'.$activity->id);
        }

        if ($evidence->onDrive()) {
            if (! $drive->connected()) {
                return $this->backToActivity($activity)
                    ->withErrors(['file' => 'This file is in Google Drive, which is not connected. Reconnect Drive before deleting it.'], 'evidence'.$activity->id);
            }
            try {
                $drive->trash($evidence->drive_file_id);
            } catch (Throwable $e) {
                report($e);

                return $this->backToActivity($activity)
                    ->withErrors(['file' => 'Google Drive did not accept the delete. Please try again.'], 'evidence'.$activity->id);
            }
        }

        // The server file, or the backup copy kept by the Drive sync.
        if ($evidence->hasServerCopy()) {
            Storage::disk('local')->delete($evidence->path);
        }

        $evidence->delete();

        return $this->backToActivity($activity)->with('status', 'Evidence "'.$evidence->original_name.'" deleted.');
    }

    private function lockedMessage(Activity $activity): string
    {
        return match ($activity->status) {
            'planned' => 'Start this step before adding evidence.',
            'in_review' => 'This step is waiting for the TIF admin\'s review. Evidence can change again if it is returned.',
            default => 'This step is done. Evidence can no longer change.',
        };
    }

    private function backToActivity(Activity $activity)
    {
        return redirect()->to(route('deliverables.show', $activity->deliverable_id).'#act-'.$activity->id);
    }
}
