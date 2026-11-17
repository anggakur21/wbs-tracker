<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Saves evidence files for a WBS step: to the connected Google Drive when there is one,
 * otherwise (or if Drive fails) to the server's private disk.
 */
class EvidenceStore
{
    public const FILE_RULES = ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip'];

    public const MESSAGES = [
        'files.required' => 'Choose at least one file.',
        'files.*.max' => 'Each file can be at most 20 MB.',
        'files.*.mimes' => 'Accepted formats: PDF, Word, Excel, PowerPoint, JPG, PNG, ZIP.',
    ];

    public function __construct(private readonly GoogleDrive $drive) {}

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array{count:int, toDrive:int, fellBack:int}
     */
    public function storeAll(array $files, Activity $activity, User $uploader): array
    {
        $deliverable = $activity->deliverable;
        $useDrive = $this->drive->connected();
        $toDrive = 0;
        $fellBack = 0;

        foreach ($files as $file) {
            $record = [
                'uploaded_by' => $uploader->id,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];

            if ($useDrive) {
                try {
                    $fileId = $this->drive->upload($file, $activity);
                    $activity->evidences()->create($record + ['storage' => 'google', 'drive_file_id' => $fileId, 'path' => 'gdrive:'.$fileId]);
                    $toDrive++;

                    continue;
                } catch (Throwable $e) {
                    // Keep the partner's upload: store it on the server instead and tell them.
                    report($e);
                    $fellBack++;
                }
            }

            // Private disk; downloadable only through the access-checked route.
            $path = $file->store('evidence/'.$deliverable->project_id.'/'.$deliverable->id.'/'.$activity->id, 'local');
            $activity->evidences()->create($record + ['storage' => 'local', 'path' => $path]);
        }

        return ['count' => count($files), 'toDrive' => $toDrive, 'fellBack' => $fellBack];
    }
}
