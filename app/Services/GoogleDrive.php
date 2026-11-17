<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The admin-connected Google Drive that evidence files are stored in.
 *
 * Uses the narrow "drive.file" scope: the app can only see and change files it created,
 * never the rest of the connected Drive. Files are kept in
 *   <App folder> / <Project> / <Deliverable> / <WBS step> / file
 * and are downloaded through the app, so the same access rules apply as for local files.
 */
class GoogleDrive
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    private const FOLDER = 'application/vnd.google-apps.folder';

    private const SCOPES = 'https://www.googleapis.com/auth/drive.file openid email profile';

    private const SETTING = 'google_drive';

    private const TOKEN_CACHE = 'google_drive.access_token';

    /** Client ID and secret are set in .env (GOOGLE_DRIVE_CLIENT_ID / GOOGLE_DRIVE_CLIENT_SECRET). */
    public function configured(): bool
    {
        return filled(config('services.google_drive.client_id')) && filled(config('services.google_drive.client_secret'));
    }

    public function connected(): bool
    {
        return $this->configured() && filled($this->connection()['refresh_token'] ?? null);
    }

    /** The stored connection: email, name, root folder, who connected it and when. */
    public function connection(): array
    {
        return Setting::get(self::SETTING, []);
    }

    public function redirectUri(): string
    {
        return config('services.google_drive.redirect') ?: route('admin.drive.callback');
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',          // always return a refresh token
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** Finishes the OAuth flow: stores the refresh token and creates the app's folder in that Drive. */
    public function connect(string $code, int $connectedBy): array
    {
        $tokens = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        if (empty($tokens['refresh_token'])) {
            throw new RuntimeException('Google did not return a refresh token. Remove the app from your Google account permissions and connect again.');
        }
        if (! str_contains($tokens['scope'] ?? '', 'auth/drive.file')) {
            throw new RuntimeException('Google Drive access was not granted. Connect again and allow access to Google Drive.');
        }

        $this->cacheToken($tokens);

        $user = $this->api()->get(self::API.'/about', ['fields' => 'user(displayName,emailAddress)'])->throw()->json('user');
        $root = $this->createFolder(config('app.name', 'TIF PMO').' – Evidence', null, ['id', 'webViewLink']);

        $connection = [
            'refresh_token' => $tokens['refresh_token'],
            'email' => $user['emailAddress'] ?? null,
            'name' => $user['displayName'] ?? null,
            'root_folder_id' => $root['id'],
            'root_folder_url' => $root['webViewLink'] ?? null,
            'connected_at' => now()->toIso8601String(),
            'connected_by' => $connectedBy,
        ];
        Setting::put(self::SETTING, $connection);

        return $connection;
    }

    /** Revokes the token at Google and forgets the connection. Files already in Drive stay there. */
    public function disconnect(): void
    {
        $refresh = $this->connection()['refresh_token'] ?? null;
        if ($refresh) {
            rescue(fn () => Http::asForm()->post(self::REVOKE_URL, ['token' => $refresh]), report: false);
        }
        Cache::forget(self::TOKEN_CACHE);
        Setting::forget(self::SETTING);
    }

    /** Uploads one evidence file into the step's folder. Returns the Drive file id. */
    public function upload(UploadedFile $file, Activity $activity): string
    {
        return $this->uploadPath($file->getRealPath(), $file->getClientOriginalName(), $file->getMimeType(), (int) $file->getSize(), $activity);
    }

    /** Uploads a file already on the server's disk (used by the sync) into the step's folder. */
    public function uploadPath(string $realPath, string $name, ?string $mime, int $size, Activity $activity): string
    {
        $folder = $this->folderFor($activity);
        $mime = $mime ?: 'application/octet-stream';

        // Resumable upload: one request for the metadata, one for the bytes (handles files up to 20 MB+).
        $session = $this->api()
            ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) $size])
            ->post(self::UPLOAD_API.'/files?uploadType=resumable&fields=id', [
                'name' => $name,
                'parents' => [$folder],
            ])->throw();

        $location = $session->header('Location');
        if (! $location) {
            throw new RuntimeException('Google Drive did not start the upload.');
        }

        $stream = fopen($realPath, 'rb');
        try {
            $id = $this->api()->withBody($stream, $mime)->put($location)->throw()->json('id');
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $id ?: throw new RuntimeException('Google Drive did not return the uploaded file.');
    }

    /** Streams a file from Drive to the browser as a download. */
    public function download(string $fileId, string $filename, ?string $mime): StreamedResponse
    {
        $response = $this->api()->withOptions(['stream' => true])
            ->get(self::API.'/files/'.$fileId, ['alt' => 'media', 'supportsAllDrives' => 'true'])->throw();

        $body = $response->toPsrResponse()->getBody();

        return response()->streamDownload(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(1024 * 64);
            }
        }, $filename, ['Content-Type' => $mime ?: 'application/octet-stream']);
    }

    /** Moves the file to the Drive's trash (recoverable there for 30 days). */
    public function trash(string $fileId): void
    {
        $response = $this->api()->patch(self::API.'/files/'.$fileId, ['trashed' => true]);
        if ($response->status() !== 404) {
            $response->throw();
        }
    }

    /** Project / Deliverable / Step folders under the app folder, created on first use and remembered. */
    private function folderFor(Activity $activity): string
    {
        $root = $this->connection()['root_folder_id'] ?? throw new RuntimeException('Google Drive is not connected.');
        $deliverable = $activity->deliverable;
        $project = $deliverable->project;

        $projectFolder = $this->rememberFolder($root, 'project', $project->id, Str::limit($project->name, 120, ''), $root);
        $deliverableFolder = $this->rememberFolder($root, 'deliverable', $deliverable->id, Str::limit($deliverable->name, 120, ''), $projectFolder);

        return $this->rememberFolder($root, 'activity', $activity->id,
            str_pad((string) $activity->sort_order, 2, '0', STR_PAD_LEFT).' '.Str::limit($activity->name, 110, ''), $deliverableFolder);
    }

    private function rememberFolder(string $root, string $type, int $id, string $name, string $parent): string
    {
        $key = ['root_id' => $root, 'owner_type' => $type, 'owner_id' => $id];
        $known = DB::table('drive_folders')->where($key)->value('folder_id');

        // Re-create the folder if someone deleted or trashed it in Drive.
        if ($known) {
            $meta = $this->api()->get(self::API.'/files/'.$known, ['fields' => 'id,trashed']);
            if ($meta->successful() && ! $meta->json('trashed')) {
                return $known;
            }
        }

        $folderId = $this->createFolder($name, $parent)['id'];
        DB::table('drive_folders')->updateOrInsert($key, ['folder_id' => $folderId, 'created_at' => now(), 'updated_at' => now()]);

        return $folderId;
    }

    private function createFolder(string $name, ?string $parent, array $fields = ['id']): array
    {
        return $this->api()->post(self::API.'/files?fields='.implode(',', $fields), array_filter([
            'name' => $name,
            'mimeType' => self::FOLDER,
            'parents' => $parent ? [$parent] : null,
        ]))->throw()->json();
    }

    private function api(): PendingRequest
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(120);
    }

    /** Short-lived access token, refreshed from the stored refresh token when it expires. */
    private function accessToken(): string
    {
        if ($token = Cache::get(self::TOKEN_CACHE)) {
            return $token;
        }

        $refresh = $this->connection()['refresh_token'] ?? throw new RuntimeException('Google Drive is not connected.');
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'refresh_token' => $refresh,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->json('error') === 'invalid_grant') {
            throw new RuntimeException('The Google Drive connection has expired or was revoked. An admin needs to reconnect it.');
        }

        return $this->cacheToken($response->throw()->json());
    }

    private function cacheToken(array $tokens): string
    {
        $token = $tokens['access_token'] ?? throw new RuntimeException('Google did not return an access token.');
        Cache::put(self::TOKEN_CACHE, $token, now()->addSeconds(max(60, (int) ($tokens['expires_in'] ?? 3600) - 120)));

        return $token;
    }
}
