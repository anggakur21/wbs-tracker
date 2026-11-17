<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Activity extends Model
{
    protected $fillable = [
        'deliverable_id', 'phase', 'sort_order', 'name', 'weight', 'is_mandatory', 'circulation',
        'plan_start', 'plan_finish', 'evidence_required',
        'progress', 'status', 'actual_start', 'actual_finish', 'note', 'updated_by',
    ];

    protected $attributes = ['status' => 'planned', 'progress' => 0];

    protected function casts(): array
    {
        return [
            'plan_start' => 'date',
            'plan_finish' => 'date',
            'actual_start' => 'date',
            'actual_finish' => 'date',
            'weight' => 'float',
            'is_mandatory' => 'boolean',
            'progress' => 'integer',
        ];
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class)->latest();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Share of this activity (0..1) the plan expects to be finished before day $date.
     * Linear per day between plan start and plan finish; today's work is not yet expected.
     */
    public function plannedFraction(Carbon $date): float
    {
        $start = $this->plan_start->copy()->startOfDay();
        $finish = $this->plan_finish->copy()->startOfDay();
        $duration = max(1, self::dayGap($start, $finish) + 1);
        $elapsed = self::dayGap($start, $date->copy()->startOfDay());

        return min(1.0, max(0.0, $elapsed / $duration));
    }

    /** Workflow status of a step, in order. */
    public const STATUSES = [
        'planned' => 'Planned',
        'in_progress' => 'In Progress',
        'in_review' => 'In Review',
        'done' => 'Done',
    ];

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Planned';
    }

    public function isPlanned(): bool
    {
        return $this->status === 'planned';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isInReview(): bool
    {
        return $this->status === 'in_review';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    /** Progress, notes and evidence can only change while the step is in progress. */
    public function isEditable(): bool
    {
        return $this->isInProgress();
    }

    /**
     * Where the step stands against its plan dates (separate from the workflow status):
     * done | review | late | behind | on_track | upcoming
     */
    public function schedule(?Carbon $date = null): string
    {
        $date = ($date ?? today())->copy()->startOfDay();

        if ($this->isDone()) {
            return 'done';
        }
        if ($this->isInReview()) {
            return 'review';            // submitted: waiting for the admin, not the partner
        }
        if ($date->gt($this->plan_finish)) {
            return 'late';
        }
        if ($date->gte($this->plan_start)) {
            return $this->progress < $this->plannedFraction($date) * 100 ? 'behind' : 'on_track';
        }

        return 'upcoming';
    }

    public function scheduleLabel(): string
    {
        return [
            'done' => 'Done',
            'review' => 'Waiting for review',
            'late' => 'Overdue',
            'behind' => 'Behind plan',
            'on_track' => 'On track',
            'upcoming' => $this->isPlanned() ? 'Not yet due' : 'Started early',
        ][$this->schedule()];
    }

    /** True when the plan says work should have begun but nobody pressed Start. */
    public function shouldHaveStarted(?Carbon $date = null): bool
    {
        return $this->isPlanned() && ($date ?? today())->copy()->startOfDay()->gte($this->plan_start);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ActivityReview::class)->latest('submitted_at')->latest('id');
    }

    /** The open submission, or the last decision. */
    public function latestReview(): ?ActivityReview
    {
        return $this->relationLoaded('reviews') ? $this->reviews->first() : $this->reviews()->first();
    }

    /** The admin's rejection, while the step is back in progress for revision. */
    public function returnedReview(): ?ActivityReview
    {
        $latest = $this->latestReview();

        return $this->isInProgress() && $latest?->isRejected() ? $latest : null;
    }

    private static function dayGap(Carbon $from, Carbon $to): int
    {
        return (int) round(($to->getTimestamp() - $from->getTimestamp()) / 86400);
    }
}
