<?php

namespace App\Models;

use App\Models\Concerns\HasScheduleStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Project extends Model
{
    use HasScheduleStatus;

    protected $fillable = ['name', 'contract_number', 'contract_value', 'partner_id', 'created_by'];

    public function deliverables(): HasMany
    {
        return $this->hasMany(Deliverable::class)->orderBy('sort_order');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** Admins see everything; partner staff see the projects of their own company. */
    public function accessibleBy(User $user): bool
    {
        return $user->isAdmin() || ($user->partner_id !== null && (int) $this->partner_id === (int) $user->partner_id);
    }

    /** Project plan % = average of its deliverables (each deliverable counts equally). */
    public function planPercent(?Carbon $date = null): float
    {
        return round((float) $this->deliverables->avg(fn (Deliverable $d) => $d->planPercent($date)), 1);
    }

    /** Project actual % = average of its deliverables. */
    public function actualPercent(): float
    {
        return round((float) $this->deliverables->avg(fn (Deliverable $d) => $d->actualPercent()), 1);
    }

    public function scheduleActivities(): Collection
    {
        return $this->deliverables->flatMap(fn (Deliverable $d) => $d->activities)->values();
    }

    public function laggedDeliverableCount(): int
    {
        return $this->deliverables->filter(fn (Deliverable $d) => $d->status() === 'lagged')->count();
    }
}
