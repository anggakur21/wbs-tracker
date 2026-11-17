<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Evidence extends Model
{
    protected $table = 'evidences';

    protected $fillable = ['activity_id', 'uploaded_by', 'original_name', 'path', 'storage', 'drive_file_id', 'size', 'mime'];

    public function onDrive(): bool
    {
        return $this->storage === 'google';
    }

    /**
     * A copy on the server's private disk. Files uploaded straight to Drive have none
     * (path "gdrive:<id>"); files synced from the server to Drive keep theirs as a backup.
     */
    public function hasServerCopy(): bool
    {
        return $this->path !== null && ! str_starts_with($this->path, 'gdrive:');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : max(1, (int) round($this->size / 1024)).' KB';
    }
}
