<?php

namespace Tests\Feature;

use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsWbs;
use Tests\TestCase;

class WbsPermissionsTest extends TestCase
{
    use BuildsWbs, RefreshDatabase;

    public static function partnerRoles(): array
    {
        return [['project_manager'], ['project_admin'], ['business_analyst']];
    }

    #[DataProvider('partnerRoles')]
    public function test_each_partner_role_can_update_progress_on_its_company_projects(string $role): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $activity = $this->projectFor($partner);
        $user = $this->partnerUser($partner, $role);

        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('Billing revamp');
        $this->actingAs($user)->put(route('activities.update', $activity), ['progress' => 40])->assertRedirect();

        $this->assertSame(40, $activity->fresh()->progress);
        $this->assertSame($user->id, $activity->fresh()->updated_by);
    }

    #[DataProvider('partnerRoles')]
    public function test_partner_roles_cannot_change_the_baseline(string $role): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $activity = $this->projectFor($partner);
        $user = $this->partnerUser($partner, $role);

        $this->actingAs($user)->put(route('admin.activities.plan', $activity), [
            'weight' => 50, 'plan_start' => '2026-01-01', 'plan_finish' => '2026-02-01',
        ])->assertForbidden();

        $this->assertEquals(100, $activity->fresh()->weight);
        $this->actingAs($user)->get(route('deliverables.show', $activity->deliverable_id))
            ->assertOk()->assertDontSee('Save baseline');
    }

    public function test_tif_admin_can_change_the_baseline(): void
    {
        $activity = $this->projectFor(Partner::create(['name' => 'PT Mitra Solusi']));

        $this->actingAs($this->admin())->put(route('admin.activities.plan', $activity), [
            'weight' => 50, 'plan_start' => '2026-01-01', 'plan_finish' => '2026-02-01',
        ])->assertRedirect();

        $this->assertEquals(50, $activity->fresh()->weight);
        $this->assertSame('2026-02-01', $activity->fresh()->plan_finish->toDateString());
    }

    public function test_partner_staff_only_see_their_own_company(): void
    {
        $mine = Partner::create(['name' => 'PT Mitra Solusi']);
        $other = Partner::create(['name' => 'PT Data Nusantara']);
        $otherActivity = $this->projectFor($other);
        $otherActivity->deliverable->project->update(['name' => 'Other company project']);
        $user = $this->partnerUser($mine);

        $this->actingAs($user)->get(route('home'))->assertOk()->assertDontSee('Other company project');
        $this->actingAs($user)->get(route('projects.show', $otherActivity->deliverable->project_id))->assertForbidden();
        $this->actingAs($user)->put(route('activities.update', $otherActivity), ['progress' => 10])->assertForbidden();
    }
}
