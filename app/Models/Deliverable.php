<?php

namespace App\Models;

use App\Models\Concerns\HasScheduleStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Deliverable extends Model
{
    use HasScheduleStatus;

    protected $fillable = ['project_id', 'name', 'sort_order'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The WBS steps of this deliverable. */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderBy('sort_order');
    }

    /** Activities grouped by phase, in WBS order. */
    public function phases(): Collection
    {
        return $this->activities->groupBy('phase');
    }

    public function totalWeight(): float
    {
        return (float) $this->activities->sum('weight');
    }

    public function planPercent(?Carbon $date = null): float
    {
        return self::rollupPlan($this->activities, $date ?? today());
    }

    public function actualPercent(): float
    {
        return self::rollupActual($this->activities);
    }

    public function scheduleActivities(): Collection
    {
        return $this->activities;
    }

    public function evidenceCoverage(): int
    {
        return $this->activities->filter(fn (Activity $a) => $a->evidences->isNotEmpty())->count();
    }

    public static function rollupPlan(Collection $activities, Carbon $date): float
    {
        $total = (float) $activities->sum('weight');
        if ($total <= 0) {
            return 0.0;
        }
        $sum = $activities->sum(fn (Activity $a) => (float) $a->weight * $a->plannedFraction($date));

        return round($sum / $total * 100, 1);
    }

    public static function rollupActual(Collection $activities): float
    {
        $total = (float) $activities->sum('weight');
        if ($total <= 0) {
            return 0.0;
        }
        $sum = $activities->sum(fn (Activity $a) => (float) $a->weight * $a->progress / 100);

        return round($sum / $total * 100, 1);
    }
}
