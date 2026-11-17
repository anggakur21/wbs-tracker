<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared schedule verdict for anything that rolls up WBS activities
 * (a single deliverable, or a whole project).
 */
trait HasScheduleStatus
{
    /** Weighted cumulative plan percentage up to (the start of) $date. */
    abstract public function planPercent(?Carbon $date = null): float;

    /** Weighted actual percentage. */
    abstract public function actualPercent(): float;

    /** Every activity that counts toward this status. */
    abstract public function scheduleActivities(): Collection;

    public function deviation(): float
    {
        return round($this->actualPercent() - $this->planPercent(), 1);
    }

    /** empty | completed | not_started | lagged | on_schedule */
    public function status(): string
    {
        $activities = $this->scheduleActivities();

        if ($activities->isEmpty()) {
            return 'empty';
        }
        if ($activities->every(fn ($a) => $a->isDone())) {   // accepted by a TIF admin
            return 'completed';
        }
        if ($this->actualPercent() == 0.0 && today()->lte($activities->min('plan_start'))) {
            return 'not_started';
        }

        return $this->deviation() < 0 ? 'lagged' : 'on_schedule';
    }

    public function statusLabel(): string
    {
        return [
            'empty' => 'NO WBS',
            'completed' => 'COMPLETED',
            'not_started' => 'NOT STARTED',
            'lagged' => 'LAGGED',
            'on_schedule' => 'ON SCHEDULE',
        ][$this->status()];
    }
}
