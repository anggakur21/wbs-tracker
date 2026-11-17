<?php

namespace Tests\Feature;

use App\Models\Evidence;
use App\Models\Partner;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWbs;
use Tests\TestCase;

/** The Google APIs are faked: these tests check the app's side of the integration. */
class GoogleDriveTest extends TestCase
{
    use BuildsWbs, RefreshDatabase;

    /** @var list<string> "METHOD url" of every call made to Google */
    private array $calls = [];

    private bool $failUploads = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_drive.client_id' => 'test-client', 'services.google_drive.client_secret' => 'test-secret']);
        Storage::fake('local');

        $folders = 0;
        Http::fake(function (Request $request) use (&$folders) {
            $url = $request->url();
            $this->calls[] = $request->method().' '.$url;

            return match (true) {
                str_starts_with($url, 'https://oauth2.googleapis.com/token') => Http::response([
                    'access_token' => 'access-1', 'expires_in' => 3599, 'refresh_token' => 'refresh-1',
                    'scope' => 'https://www.googleapis.com/auth/drive.file openid email',
                ]),
                str_starts_with($url, 'https://oauth2.googleapis.com/revoke') => Http::response([]),
                str_contains($url, '/drive/v3/about') => Http::response(['user' => ['displayName' => 'TIF Drive', 'emailAddress' => 'drive@tif.example']]),
                str_starts_with($url, 'https://www.googleapis.com/upload/drive/v3/files') => $this->failUploads
                    ? Http::response(['error' => 'backend'], 500)
                    : Http::response([], 200, ['Location' => 'https://upload.test/session-1']),
                $url === 'https://upload.test/session-1' => Http::response(['id' => 'file-123']),
                $request->method() === 'POST' && str_starts_with($url, 'https://www.googleapis.com/drive/v3/files') => Http::response(['id' => 'folder-'.(++$folders), 'webViewLink' => 'https://drive.test/f']),
                $request->method() === 'GET' && str_contains($url, '/files/file-123') => Http::response('PDF-BYTES', 200, ['Content-Type' => 'application/pdf']),
                $request->method() === 'GET' && str_contains($url, '/files/folder-') => Http::response(['id' => 'folder', 'trashed' => false]),
                $request->method() === 'PATCH' => Http::response(['id' => 'file-123', 'trashed' => true]),
                default => Http::response(['error' => 'unexpected '.$url], 404),
            };
        });
    }

    public function test_admin_connects_a_drive_account(): void
    {
        $admin = $this->admin();

        $redirect = $this->actingAs($admin)->post(route('admin.drive.connect'));
        $location = $redirect->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $location);
        $this->assertStringContainsString(urlencode('auth/drive.file'), $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->actingAs($admin)->get(route('admin.drive.callback', ['state' => $query['state'], 'code' => 'auth-code']))
            ->assertRedirect(route('admin.drive.show'))->assertSessionHasNoErrors();

        $connection = Setting::get('google_drive');
        $this->assertSame('drive@tif.example', $connection['email']);
        $this->assertSame('refresh-1', $connection['refresh_token']);
        $this->assertSame('folder-1', $connection['root_folder_id']);

        // Stored encrypted, not as plain text.
        $this->assertStringNotContainsString('refresh-1', (string) Setting::query()->value('value'));

        $this->actingAs($admin)->get(route('admin.drive.show'))->assertOk()->assertSee('CONNECTED')->assertSee('drive@tif.example');
    }

    public function test_callback_with_a_wrong_state_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.drive.connect'));

        $this->actingAs($admin)->get(route('admin.drive.callback', ['state' => 'forged', 'code' => 'x']))
            ->assertSessionHasErrors('drive');
        $this->assertNull(Setting::get('google_drive'));
    }

    public function test_evidence_goes_to_drive_and_comes_back_through_the_app(): void
    {
        $this->connectDrive();
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $activity = $this->projectFor($partner);
        $user = $this->partnerUser($partner, 'business_analyst');

        $this->actingAs($user)->post(route('evidences.store', $activity), [
            'files' => [UploadedFile::fake()->create('charter.pdf', 200, 'application/pdf')],
        ])->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, 'to Google Drive'));

        $evidence = Evidence::first();
        $this->assertSame('google', $evidence->storage);
        $this->assertSame('file-123', $evidence->drive_file_id);
        $this->assertEmpty(Storage::disk('local')->allFiles());
        // Project, deliverable and step folders were created under the app folder.
        $this->assertSame(3, \DB::table('drive_folders')->count());

        $download = $this->actingAs($user)->get(route('evidences.download', $evidence));
        $download->assertOk();
        $this->assertSame('PDF-BYTES', $download->streamedContent());

        $this->actingAs($user)->delete(route('evidences.destroy', $evidence))->assertRedirect();
        $this->assertModelMissing($evidence);
        $this->assertContains('PATCH https://www.googleapis.com/drive/v3/files/file-123', $this->calls);
    }

    public function test_upload_falls_back_to_the_server_when_drive_fails(): void
    {
        $this->connectDrive();
        $this->failUploads = true;
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $activity = $this->projectFor($partner);

        $this->actingAs($this->partnerUser($partner))->post(route('evidences.store', $activity), [
            'files' => [UploadedFile::fake()->create('charter.pdf', 200, 'application/pdf')],
        ])->assertSessionHas('status', fn ($s) => str_contains($s, 'stored on the server instead'));

        $evidence = Evidence::first();
        $this->assertSame('local', $evidence->storage);
        Storage::disk('local')->assertExists($evidence->path);
    }

    public function test_without_a_connection_evidence_stays_on_the_server(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $activity = $this->projectFor($partner);

        $this->actingAs($this->partnerUser($partner))->post(route('evidences.store', $activity), [
            'files' => [UploadedFile::fake()->create('charter.pdf', 200, 'application/pdf')],
        ]);

        $this->assertSame('local', Evidence::first()->storage);
        $this->assertSame([], $this->calls);
    }

    public function test_sync_copies_server_files_to_drive_and_keeps_the_server_copy(): void
    {
        [$activity, $first, $second] = $this->serverFiles();
        $this->connectDrive();
        $admin = $this->admin();

        $this->actingAs($admin)->postJson(route('admin.drive.sync'))
            ->assertOk()->assertJson(['synced' => 2, 'failed' => [], 'remaining' => 0, 'total_in_drive' => 2]);

        foreach ([$first, $second] as $evidence) {
            $evidence->refresh();
            $this->assertSame('google', $evidence->storage);
            $this->assertSame('file-123', $evidence->drive_file_id);
            $this->assertTrue($evidence->hasServerCopy());
            Storage::disk('local')->assertExists($evidence->path);   // backup kept
        }
        // Same folder structure as new uploads: project, deliverable, step.
        $this->assertSame(3, \DB::table('drive_folders')->count());

        // Running it again finds nothing left to copy.
        $this->actingAs($admin)->postJson(route('admin.drive.sync'))->assertJson(['synced' => 0, 'remaining' => 0]);
    }

    public function test_sync_reports_files_missing_on_the_server_and_moves_on(): void
    {
        [$activity, $first, $second] = $this->serverFiles();
        Storage::disk('local')->delete($first->path);
        $this->connectDrive();

        $response = $this->actingAs($this->admin())->postJson(route('admin.drive.sync'))->assertOk();

        $this->assertSame(1, $response->json('synced'));
        $this->assertSame(0, $response->json('remaining'));
        $this->assertSame($first->id, $response->json('failed.0.id'));
        $this->assertSame('The file is missing on the server.', $response->json('failed.0.reason'));
        $this->assertSame('local', $first->fresh()->storage);
    }

    public function test_download_falls_back_to_the_server_copy_when_drive_fails(): void
    {
        [$activity, $evidence] = $this->serverFiles();
        $this->connectDrive();
        $evidence->update(['storage' => 'google', 'drive_file_id' => 'gone-from-drive']);   // Drive answers 404
        $partner = $activity->deliverable->project->partner;

        $download = $this->actingAs($this->partnerUser($partner))->get(route('evidences.download', $evidence));
        $download->assertOk();
        $this->assertSame('SERVER-BYTES-1', $download->streamedContent());
    }

    public function test_deleting_a_synced_file_removes_both_copies(): void
    {
        [$activity, $evidence] = $this->serverFiles();
        $this->connectDrive();
        $this->actingAs($this->admin())->postJson(route('admin.drive.sync'));
        $evidence->refresh();
        $partner = $activity->deliverable->project->partner;

        $this->actingAs($this->partnerUser($partner))->delete(route('evidences.destroy', $evidence))->assertRedirect();

        $this->assertModelMissing($evidence);
        Storage::disk('local')->assertMissing($evidence->path);
        $this->assertContains('PATCH https://www.googleapis.com/drive/v3/files/file-123', $this->calls);
    }

    public function test_sync_needs_a_connection_and_an_admin(): void
    {
        [$activity] = $this->serverFiles();
        $partner = $activity->deliverable->project->partner;

        $this->actingAs($this->admin())->postJson(route('admin.drive.sync'))->assertStatus(409);
        $this->connectDrive();
        $this->actingAs($this->partnerUser($partner))->postJson(route('admin.drive.sync'))->assertForbidden();
        $this->assertSame(2, Evidence::where('storage', 'local')->count());
    }

    public function test_drive_page_shows_the_sync_button_and_the_artisan_command_works(): void
    {
        $this->serverFiles();
        $this->connectDrive();

        $this->actingAs($this->admin())->get(route('admin.drive.show'))->assertOk()->assertSee('Sync 2 files');

        $this->artisan('drive:sync')->expectsOutputToContain('2 file(s) copied to Google Drive.')->assertSuccessful();
        $this->assertSame(0, Evidence::where('storage', 'local')->count());
    }

    /** A step with two evidence files that exist only on the server. */
    private function serverFiles(): array
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $activity = $this->projectFor($partner);
        $files = [];
        foreach ([1, 2] as $n) {
            $path = 'evidence/1/1/'.$activity->id.'/file-'.$n.'.pdf';
            Storage::disk('local')->put($path, 'SERVER-BYTES-'.$n);
            $files[] = $activity->evidences()->create([
                'original_name' => 'file-'.$n.'.pdf', 'path' => $path, 'storage' => 'local', 'size' => 14, 'mime' => 'application/pdf',
            ]);
        }

        return [$activity, ...$files];
    }

    private function connectDrive(): void
    {
        Setting::put('google_drive', [
            'refresh_token' => 'refresh-1', 'email' => 'drive@tif.example', 'name' => 'TIF Drive',
            'root_folder_id' => 'root-folder', 'connected_at' => now()->toIso8601String(), 'connected_by' => null,
        ]);
    }
}
