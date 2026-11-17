<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Notifications\StepReturnedEmail;
use App\Notifications\StepStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWbs;
use Tests\TestCase;

class StepWorkflowTest extends TestCase
{
    use BuildsWbs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_full_cycle_start_submit_return_resubmit_accept(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'planned');
        $pm = $this->partnerUser($partner, 'project_manager', 'pm');
        $admin = $this->admin();

        // Planned → In Progress: Start fills the actual start date.
        $this->actingAs($pm)->post(route('activities.start', $step))->assertRedirect();
        $step->refresh();
        $this->assertSame('in_progress', $step->status);
        $this->assertSame(today()->toDateString(), $step->actual_start->toDateString());

        $this->actingAs($pm)->put(route('activities.update', $step), ['progress' => 60])->assertSessionHasNoErrors();

        // In Progress → In Review: evidence is mandatory.
        $this->actingAs($pm)->post(route('activities.submit', $step), ['submission_note' => 'Ready'])
            ->assertSessionHasErrors('files', null, 'status'.$step->id);
        $this->assertSame('in_progress', $step->fresh()->status);

        $this->actingAs($pm)->post(route('activities.submit', $step), [
            'submission_note' => 'Signed charter attached',
            'files' => [UploadedFile::fake()->create('charter.pdf', 100, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $step->refresh();
        $this->assertSame('in_review', $step->status);
        $this->assertSame(100, $step->progress);
        $this->assertSame(today()->toDateString(), $step->actual_finish->toDateString());
        $this->assertSame(1, $step->evidences()->count());

        // Admins are notified of the submission.
        $this->assertSame(StepStatusChanged::SUBMITTED, $admin->notifications()->first()->data['event']);

        // Locked while in review.
        $this->actingAs($pm)->put(route('activities.update', $step), ['progress' => 50])
            ->assertSessionHasErrors('progress', null, 'activity'.$step->id);

        // Admin returns it: a reason is required; it goes back to In Progress with the earlier progress.
        $this->actingAs($admin)->post(route('activities.reject', $step), ['reason' => ''])
            ->assertSessionHasErrors('reason', null, 'status'.$step->id);
        $this->actingAs($admin)->post(route('activities.reject', $step), ['reason' => 'Signature page is missing.'])
            ->assertSessionHasNoErrors();
        $step->refresh();
        $this->assertSame('in_progress', $step->status);
        $this->assertSame(60, $step->progress);
        $this->assertNull($step->actual_finish);
        $this->assertSame('Signature page is missing.', $step->returnedReview()->reason);

        // The Project Manager is notified with the reason.
        $notice = $pm->notifications()->first();
        $this->assertSame(StepStatusChanged::RETURNED, $notice->data['event']);
        $this->assertStringContainsString('Signature page is missing.', $notice->data['message']);

        // Resubmit (existing evidence is enough) and accept.
        $this->actingAs($pm)->post(route('activities.submit', $step), ['submission_note' => 'Added signature page'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('activities.accept', $step))->assertSessionHasNoErrors();

        $step->refresh();
        $this->assertSame('done', $step->status);
        $this->assertSame(2, $step->reviews()->count());
        $this->assertSame(['accepted', 'rejected'], $step->reviews()->pluck('decision')->all());
        $this->assertContains(StepStatusChanged::ACCEPTED, $pm->notifications()->get()->pluck('data.event')->all());
        $this->assertSame('completed', $step->deliverable->fresh()->status());
    }

    public function test_only_the_project_manager_starts_and_submits(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'planned');

        foreach (['project_admin', 'business_analyst'] as $role) {
            $user = $this->partnerUser($partner, $role, $role);
            $this->actingAs($user)->post(route('activities.start', $step))->assertForbidden();
        }
        $this->actingAs($this->admin())->post(route('activities.start', $step))->assertForbidden();
        $this->assertSame('planned', $step->fresh()->status);
    }

    public function test_other_roles_update_progress_and_evidence_while_in_progress(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner);
        $ba = $this->partnerUser($partner, 'business_analyst', 'ba');

        $this->actingAs($ba)->put(route('activities.update', $step), ['progress' => 70])->assertSessionHasNoErrors();
        $this->actingAs($ba)->post(route('evidences.store', $step), ['files' => [UploadedFile::fake()->create('draft.pdf', 50, 'application/pdf')]])
            ->assertSessionHasNoErrors();

        $this->assertSame(70, $step->fresh()->progress);
        $this->assertSame(1, $step->evidences()->count());
        // 100% is reached only by submitting.
        $this->actingAs($ba)->put(route('activities.update', $step), ['progress' => 100])
            ->assertSessionHasErrors('progress', null, 'activity'.$step->id);
    }

    public function test_planned_steps_cannot_be_updated_before_start(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'planned');
        $pm = $this->partnerUser($partner);

        $this->actingAs($pm)->put(route('activities.update', $step), ['progress' => 10])
            ->assertSessionHasErrors('progress', null, 'activity'.$step->id);
        $this->actingAs($pm)->post(route('evidences.store', $step), ['files' => [UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]])
            ->assertSessionHasErrors('files', null, 'evidence'.$step->id);
        $this->actingAs($pm)->post(route('activities.submit', $step))->assertSessionHasErrors('status', null, 'status'.$step->id);
    }

    public function test_partners_cannot_review_and_admin_can_reopen_a_done_step(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'in_review');
        $pm = $this->partnerUser($partner);

        $this->actingAs($pm)->post(route('activities.accept', $step))->assertForbidden();
        $this->actingAs($pm)->post(route('activities.reject', $step), ['reason' => 'Self review'])->assertForbidden();

        $step->update(['status' => 'done', 'progress' => 100]);
        $this->actingAs($this->admin())->post(route('activities.reject', $step), ['reason' => 'Audit found an error.'])
            ->assertSessionHasNoErrors();

        $step->refresh();
        $this->assertSame('in_progress', $step->status);
        $this->assertLessThan(100, $step->progress);
        $this->assertSame(StepStatusChanged::RETURNED, $pm->notifications()->first()->data['event']);
    }

    public function test_returning_a_step_emails_the_project_managers(): void
    {
        Notification::fake();
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'in_review');
        $pm = $this->partnerUser($partner, 'project_manager', 'pm');
        $pm->update(['email' => 'rina@mitra.example']);
        $pmNoEmail = $this->partnerUser($partner, 'project_manager', 'pm2');
        $ba = $this->partnerUser($partner, 'business_analyst', 'ba');
        $ba->update(['email' => 'ba@mitra.example']);

        $this->actingAs($this->admin())->post(route('activities.reject', $step), ['reason' => 'Signature page is missing.'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'by email (rina@mitra.example)') && str_contains($s, 'Partner Person (no email address)'));

        Notification::assertSentTo($pm, StepReturnedEmail::class, function ($n) use ($pm, $step) {
            $mail = $n->toMail($pm);

            return str_contains($mail->subject, 'Revision needed: Project Charter')
                && in_array('Signature page is missing.', $mail->introLines, true)
                && $mail->actionUrl === route('deliverables.show', $step->deliverable_id).'#act-'.$step->id;
        });
        Notification::assertNotSentTo($pmNoEmail, StepReturnedEmail::class);
        Notification::assertNotSentTo($ba, StepReturnedEmail::class);       // only Project Managers
        Notification::assertSentTo($pmNoEmail, StepStatusChanged::class);  // still notified in the app
    }

    public function test_a_failing_mail_server_does_not_block_the_return(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'in_review');
        $pm = $this->partnerUser($partner);
        $pm->update(['email' => 'rina@mitra.example']);

        $this->actingAs($this->admin())->post(route('activities.reject', $step), ['reason' => 'Signature page is missing.'])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'the email could not be sent'));

        $this->assertSame('in_progress', $step->fresh()->status);
        $this->assertSame(1, $pm->notifications()->count());
    }

    public function test_notifications_page_and_opening_marks_read(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'in_review');
        $pm = $this->partnerUser($partner);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('activities.reject', $step), ['reason' => 'Wrong template used.']);

        $this->actingAs($pm)->get(route('notifications.index'))->assertOk()
            ->assertSee('Returned for revision: Project Charter')->assertSee('Wrong template used.');
        $this->actingAs($pm)->get(route('home'))->assertSee('nav-badge', false)->assertSee('Returned for revision');

        $id = $pm->notifications()->first()->id;
        $this->actingAs($pm)->get(route('notifications.open', $id))
            ->assertRedirect(route('deliverables.show', $step->deliverable_id).'#act-'.$step->id);
        $this->assertSame(0, $pm->fresh()->unreadNotifications()->count());
    }

    public function test_deliverable_page_shows_the_right_action_per_role(): void
    {
        $partner = Partner::create(['name' => 'PT Mitra Solusi']);
        $step = $this->projectFor($partner, 'planned');
        $url = route('deliverables.show', $step->deliverable_id);

        $this->actingAs($this->partnerUser($partner, 'project_manager', 'pm'))->get($url)->assertSee('Start step');
        $this->actingAs($this->partnerUser($partner, 'business_analyst', 'ba'))->get($url)
            ->assertDontSee('Start step')->assertSee('Waiting for the partner');

        $step->update(['status' => 'in_review']);
        $this->actingAs($this->admin())->get($url)->assertSee('Accept · mark Done')->assertSee('Return to In Progress');
    }
}
