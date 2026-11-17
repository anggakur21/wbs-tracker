<?php

namespace Tests\Feature;

use App\Models\Partner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWbs;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BuildsWbs, RefreshDatabase;

    public function test_dashboard_is_the_main_page_and_projects_list_moved(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $this->projectFor($partner);
        $user = $this->partnerUser($partner);

        $this->actingAs($user)->get('/')->assertOk()->assertSee('Overall progress')->assertSee('Billing revamp');
        $this->actingAs($user)->get(route('projects.index'))->assertOk()->assertSee('Billing revamp');
    }

    public function test_it_lists_overdue_upcoming_and_missing_evidence_steps(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $first = $this->projectFor($partner);
        $deliverable = $first->deliverable;

        $first->update(['name' => 'Overdue charter', 'plan_start' => today()->subDays(20), 'plan_finish' => today()->subDays(6), 'progress' => 40]);
        $deliverable->activities()->create(['phase' => 'Planning', 'name' => 'Upcoming design', 'weight' => 10, 'sort_order' => 2,
            'plan_start' => today()->subDays(2), 'plan_finish' => today()->addDays(5), 'progress' => 50]);
        $deliverable->activities()->create(['phase' => 'Planning', 'name' => 'Finished no file', 'weight' => 10, 'sort_order' => 3, 'status' => 'done',
            'plan_start' => today()->subDays(9), 'plan_finish' => today()->subDays(3), 'progress' => 100]);
        $deliverable->activities()->create(['phase' => 'Closing', 'name' => 'Far future step', 'weight' => 10, 'sort_order' => 4,
            'plan_start' => today()->addDays(60), 'plan_finish' => today()->addDays(70), 'progress' => 0]);

        $response = $this->actingAs($this->partnerUser($partner))->get(route('home'))->assertOk();

        $response->assertSeeInOrder(['Overdue steps', 'Overdue charter', '6 days late']);
        $response->assertSeeInOrder(['Due in the next 14 days', 'Upcoming design', 'in 5 days']);
        $response->assertSeeInOrder(['Evidence missing', 'Finished no file']);
        $response->assertDontSee('Far future step');
        $response->assertSee('#act-'.$first->id, false);
    }

    public function test_partner_staff_see_only_their_company_and_no_admin_section(): void
    {
        $mine = Partner::create(['name' => 'PT Mitra Solusi']);
        $other = Partner::create(['name' => 'PT Data Nusantara']);
        $this->projectFor($mine);
        $otherStep = $this->projectFor($other);
        $otherStep->deliverable->project->update(['name' => 'Other company project']);
        $otherStep->update(['name' => 'Secret overdue step', 'plan_finish' => today()->subDays(3), 'plan_start' => today()->subDays(9)]);

        $this->actingAs($this->partnerUser($mine))->get(route('home'))->assertOk()
            ->assertSee('Billing revamp')
            ->assertDontSee('Other company project')
            ->assertDontSee('Secret overdue step')
            ->assertDontSee('Admin to-do')
            ->assertDontSee('New project');
    }

    public function test_admin_sees_every_project_and_the_admin_todo(): void
    {
        $withStaff = Partner::create(['name' => 'PT Mitra Solusi']);
        $noStaff = Partner::create(['name' => 'PT Tanpa Akun']);
        $this->projectFor($withStaff);
        $this->projectFor($noStaff)->deliverable->project->update(['name' => 'Second project']);
        $this->partnerUser($withStaff)->forceFill(['must_change_password' => true, 'name' => 'Waiting Person'])->save();

        $this->actingAs($this->admin())->get(route('home'))->assertOk()
            ->assertSee('Billing revamp')->assertSee('Second project')
            ->assertSee('Admin to-do')
            ->assertSee('PT Tanpa Akun')            // partner without accounts
            ->assertSee('Waiting Person')           // account still on a one-time password
            ->assertSee('Google Drive is not connected')
            ->assertSee('New project');
    }

    public function test_empty_dashboard_for_a_company_without_projects(): void
    {
        $user = $this->partnerUser(Partner::create(['name' => 'PT Mitra Solusi']));

        $this->actingAs($user)->get(route('home'))->assertOk()->assertSee('No projects yet');
    }
}
