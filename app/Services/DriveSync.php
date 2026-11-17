<?php

namespace App\Services;

use App\Models\Evidence;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Copies evidence files that exist only on the server to the connected Google Drive, into the
 * same Project / Deliverable / Step folders as new uploads. The server copy is kept as a backup,
 * so both places hold the same files. Runs in small batches so a page request never times out.
 */
class DriveSync
{
    public function __construct(private readonly GoogleDrive $drive) {}

    /** Evidence records whose file is on the server but not yet in Drive. */
    public function pendingQuery()
    {
        return Evidence::where('storage', 'local')->orderBy('id');
    }

    public function pendingCount(): int
    {
        return $this->pendingQuery()->count();
    }

    /**
     * Syncs up to $limit files (or until $seconds have passed).
     *
     * @param  list<int>  $skip  ids that already failed in this run, so the run can move past them
     * @return array{synced:int, failed:list<array{id:int, name:string, reason:string}>, remaining:int}
     */
    public function batch(int $limit = 15, int $seconds = 20, array $skip = []): array
    {
        if (! $this->drive->connected()) {
            throw new RuntimeException('Google Drive is not connected.');
        }

        $deadline = microtime(true) + $seconds;
        $synced = 0;
        $failed = [];

        $evidences = $this->pendingQuery()->whereNotIn('id', $skip ?: [0])
            ->with('activity.deliverable.project')->limit($limit)->get();

        foreach ($evidences as $evidence) {
            if (microtime(true) > $deadline) {
                break;
            }

            try {
                $this->syncOne($evidence);
                $synced++;
            } catch (Throwable $e) {
                report($e);
                $failed[] = ['id' => $evidence->id, 'name' => $evidence->original_name, 'reason' => $this->reason($e)];
            }
        }

        $skip = array_merge($skip, array_column($failed, 'id'));

        return [
            'synced' => $synced,
            'failed' => $failed,
            'remaining' => $this->pendingQuery()->whereNotIn('id', $skip ?: [0])->count(),
        ];
    }

    public function syncOne(Evidence $evidence): void
    {
        $disk = Storage::disk('local');
        if (! $evidence->hasServerCopy() || ! $disk->exists($evidence->path)) {
            throw new RuntimeException('The file is missing on the server.');
        }

        $fileId = $this->drive->uploadPath(
            $disk->path($evidence->path),
            $evidence->original_name,
            $evidence->mime,
            (int) $disk->size($evidence->path),
            $evidence->activity,
        );

        // From now on the app reads it from Drive; the server copy stays as the backup.
        $evidence->update(['storage' => 'google', 'drive_file_id' => $fileId]);
    }

    private function reason(Throwable $e): string
    {
        return $e instanceof RuntimeException ? $e->getMessage() : 'Google Drive did not accept the upload.';
    }
}
