<?php

namespace App\Console\Commands;

use App\Services\DriveSync;
use App\Services\GoogleDrive;
use Illuminate\Console\Command;

/** Same as the "Sync to Google Drive" button on Admin › Google Drive, for large backlogs. */
class SyncEvidenceToDrive extends Command
{
    protected $signature = 'drive:sync';

    protected $description = 'Copy evidence files that exist only on the server to the connected Google Drive (server copies are kept)';

    public function handle(DriveSync $sync, GoogleDrive $drive): int
    {
        if (! $drive->connected()) {
            $this->error('Google Drive is not connected. An admin connects it on Admin › Google Drive.');

            return self::FAILURE;
        }

        $total = $sync->pendingCount();
        if ($total === 0) {
            $this->info('Nothing to sync: every server file is already in Google Drive.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $skip = [];
        $synced = 0;
        $failed = [];

        do {
            $result = $sync->batch(limit: 25, seconds: 120, skip: $skip);
            $synced += $result['synced'];
            $failed = array_merge($failed, $result['failed']);
            $skip = array_column($failed, 'id');
            $bar->advance($result['synced'] + count($result['failed']));
        } while ($result['remaining'] > 0 && ($result['synced'] + count($result['failed'])) > 0);

        $bar->finish();
        $this->newLine(2);
        $this->info("{$synced} file(s) copied to Google Drive.");

        foreach ($failed as $f) {
            $this->warn("Not copied: {$f['name']} (evidence #{$f['id']}): {$f['reason']}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
