<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One submission of a WBS step for review, and the TIF admin's decision on it. */
class ActivityReview extends Model
{
    protected $fillable = [
        'activity_id', 'submitted_by', 'submitted_at', 'submission_note', 'progress_before',
        'decision', 'reviewed_by', 'reviewed_at', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'progress_before' => 'integer',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isRejected(): bool
    {
        return $this->decision === 'rejected';
    }
}
