<?php

namespace Tests\Concerns;

use App\Models\Activity;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;

/** Small builders for feature tests (the app has no model factories). */
trait BuildsWbs
{
    protected function admin(): User
    {
        return User::create(['name' => 'TIF Admin', 'username' => 'tif-admin', 'role' => 'admin', 'password' => 'secret-password']);
    }

    protected function partnerUser(Partner $partner, string $role = 'project_manager', string $username = 'pm-user'): User
    {
        return $partner->users()->create([
            'name' => 'Partner Person', 'username' => $username, 'role' => 'partner',
            'partner_role' => $role, 'password' => 'secret-password',
        ]);
    }

    /** A project with one deliverable and one step, already In Progress unless told otherwise. */
    protected function projectFor(Partner $partner, string $status = 'in_progress'): Activity
    {
        $project = Project::create(['name' => 'Billing revamp', 'partner_id' => $partner->id]);
        $deliverable = $project->deliverables()->create(['name' => 'Billing module', 'sort_order' => 1]);

        return $deliverable->activities()->create([
            'phase' => 'Preparing', 'name' => 'Project Charter', 'weight' => 100, 'sort_order' => 1,
            'plan_start' => today()->subDays(5)->toDateString(), 'plan_finish' => today()->addDays(5)->toDateString(),
            'status' => $status, 'actual_start' => $status === 'planned' ? null : today()->subDays(4)->toDateString(),
        ]);
    }
}
