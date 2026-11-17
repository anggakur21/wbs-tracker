<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Project;
use App\Models\WbsItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWbs;
use Tests\TestCase;

class WbsItemsAndProjectCreationTest extends TestCase
{
    use BuildsWbs, RefreshDatabase;

    public function test_standard_items_are_seeded_with_if_any_steps_optional(): void
    {
        $this->assertSame(26, WbsItem::count());
        $this->assertFalse(WbsItem::where('name', 'Migration Plan Document (if any)')->value('is_mandatory'));
        $this->assertTrue((bool) WbsItem::where('name', 'Project Charter')->value('is_mandatory'));
        $this->assertSame(0, WbsItem::where('circulation', 'like', '%SMILE%')->count());
    }

    public function test_admin_edits_reorders_adds_and_removes_items(): void
    {
        $items = WbsItem::orderBy('sort_order')->take(2)->get();
        [$first, $second] = [$items[0], $items[1]];

        $this->actingAs($this->admin())->put(route('admin.wbs-items.update'), ['items' => [
            // second item moved to the top and made optional
            ['id' => $second->id, 'phase' => 'Preparing', 'name' => 'Project Charter', 'weight' => 40, 'is_mandatory' => 0, 'offset_start' => 0, 'offset_finish' => 1],
            ['id' => $first->id, 'phase' => 'Preparing', 'name' => 'Team Building', 'weight' => 40, 'is_mandatory' => 1, 'offset_start' => 0, 'offset_finish' => 0],
            ['id' => null, 'phase' => 'Closing', 'name' => 'Handover', 'weight' => 20, 'is_mandatory' => 1, 'offset_start' => 5, 'offset_finish' => 6, 'evidence_required' => 'BA Handover'],
        ]])->assertRedirect(route('admin.wbs-items.index'))->assertSessionHasNoErrors();

        $this->assertSame(['Project Charter', 'Team Building', 'Handover'], WbsItem::orderBy('sort_order')->pluck('name')->all());
        $this->assertFalse(WbsItem::find($second->id)->is_mandatory);
        $this->assertSame('BA Handover', WbsItem::where('name', 'Handover')->value('evidence_required'));
    }

    public function test_partner_staff_cannot_configure_items(): void
    {
        $user = $this->partnerUser(Partner::create(['name' => 'PT Mitra Solusi']));

        $this->actingAs($user)->get(route('admin.wbs-items.index'))->assertForbidden();
        $this->actingAs($user)->put(route('admin.wbs-items.update'), ['items' => []])->assertForbidden();
    }

    public function test_each_deliverable_gets_its_own_plan_and_optional_steps(): void
    {
        WbsItem::query()->delete();
        $charter = WbsItem::create(['sort_order' => 1, 'phase' => 'Preparing', 'name' => 'Project Charter', 'weight' => 60, 'is_mandatory' => true]);
        $migration = WbsItem::create(['sort_order' => 2, 'phase' => 'Implementation', 'name' => 'Migration (if any)', 'weight' => 40, 'is_mandatory' => false]);
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);

        $this->actingAs($this->admin())->post(route('admin.projects.store'), [
            'name' => 'Billing revamp', 'partner_id' => $partner->id, 'plan_mode' => 'manual',
            'deliverables' => [0 => 'Billing module', 3 => 'Customer portal'],
            'plans' => [
                0 => [
                    $charter->id => ['weight' => 60, 'plan_start' => '2026-01-05', 'plan_finish' => '2026-01-09'],
                    $migration->id => ['include' => 1, 'weight' => 40, 'plan_start' => '2026-02-01', 'plan_finish' => '2026-02-10'],
                ],
                3 => [
                    // different dates; optional step left out (its fields are not sent)
                    $charter->id => ['weight' => 100, 'plan_start' => '2026-03-02', 'plan_finish' => '2026-03-06'],
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $project = Project::with('deliverables.activities')->first();
        [$billing, $portal] = [$project->deliverables[0], $project->deliverables[1]];

        $this->assertSame('Billing module', $billing->name);
        $this->assertSame(['Project Charter', 'Migration (if any)'], $billing->activities->pluck('name')->all());
        $this->assertFalse($billing->activities[1]->is_mandatory);

        $this->assertSame('Customer portal', $portal->name);
        $this->assertSame(['Project Charter'], $portal->activities->pluck('name')->all());
        $this->assertSame('2026-03-02', $portal->activities[0]->plan_start->toDateString());
        $this->assertEquals(100, $portal->activities[0]->weight);
    }

    public function test_mandatory_steps_need_dates_in_every_tab(): void
    {
        WbsItem::query()->delete();
        $charter = WbsItem::create(['sort_order' => 1, 'phase' => 'Preparing', 'name' => 'Project Charter', 'weight' => 100, 'is_mandatory' => true]);
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);

        $this->actingAs($this->admin())->post(route('admin.projects.store'), [
            'name' => 'Billing revamp', 'partner_id' => $partner->id, 'plan_mode' => 'manual',
            'deliverables' => [0 => 'Billing module', 1 => 'Customer portal'],
            'plans' => [
                0 => [$charter->id => ['weight' => 100, 'plan_start' => '2026-01-05', 'plan_finish' => '2026-01-09']],
                1 => [],   // mandatory step missing in the second tab
            ],
        ])->assertSessionHasErrors("plans.1.{$charter->id}.plan_start");

        $this->assertSame(0, Project::count());
    }

    public function test_new_project_form_shows_one_tab_per_deliverable(): void
    {
        Partner::create(['name' => 'PT Mitra Solusi']);

        $this->actingAs($this->admin())->get(route('admin.projects.create'))
            ->assertOk()
            ->assertSee('Add deliverable')
            ->assertSee('id="panel-0"', false)
            ->assertSee('id="tab-template"', false)
            ->assertSee('Mandatory')->assertSee('Optional');
    }
}
