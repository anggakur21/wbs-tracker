<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\StepReturnedEmail;
use App\Notifications\StepStatusChanged;
use App\Services\EvidenceStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The status workflow of one WBS step:
 *   Planned → (PM: Start) → In Progress → (PM: Submit with evidence) → In Review
 *   In Review → (TIF admin: Accept) → Done
 *   In Review or Done → (TIF admin: Return with a reason) → In Progress, PMs notified
 */
class ActivityStatusController extends Controller
{
    public function start(Request $request, Activity $activity)
    {
        $this->authorizePartnerManager($request, $activity);

        if (! $activity->isPlanned()) {
            return $this->back($activity)->withErrors(['status' => 'This step has already been started.'], 'status'.$activity->id);
        }

        $activity->update([
            'status' => 'in_progress',
            'actual_start' => today()->toDateString(),
            'updated_by' => $request->user()->id,
        ]);

        return $this->back($activity)->with('status', '"'.$activity->name.'" started. Actual start set to '.today()->format('j M Y').'.');
    }

    public function submit(Request $request, Activity $activity, EvidenceStore $store)
    {
        $this->authorizePartnerManager($request, $activity);

        if (! $activity->isInProgress()) {
            return $this->back($activity)->withErrors(['status' => 'Only a step in progress can be submitted for review.'], 'status'.$activity->id);
        }

        $bag = 'status'.$activity->id;
        $request->validateWithBag($bag, [
            'submission_note' => ['nullable', 'string', 'max:2000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => EvidenceStore::FILE_RULES,
        ], EvidenceStore::MESSAGES);

        // Evidence is mandatory: files already on the step, or attached to this submission.
        $newFiles = $request->file('files', []);
        if ($activity->evidences()->doesntExist() && ! $newFiles) {
            return $this->back($activity)
                ->withErrors(['files' => 'Upload the evidence before submitting. "'.($activity->evidence_required ?: 'Evidence').'" is required.'], $bag)
                ->withInput();
        }

        $user = $request->user();
        $result = $newFiles ? $store->storeAll($newFiles, $activity, $user) : null;

        DB::transaction(function () use ($activity, $request, $user) {
            $activity->reviews()->create([
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'submission_note' => $request->input('submission_note'),
                'progress_before' => $activity->progress,
            ]);
            $activity->update([
                'status' => 'in_review',
                'progress' => 100,
                'actual_finish' => today()->toDateString(),
                'updated_by' => $user->id,
            ]);
        });

        Notification::send(User::where('role', 'admin')->get(), new StepStatusChanged(StepStatusChanged::SUBMITTED, $activity, $user));

        $message = '"'.$activity->name.'" submitted for review. The TIF admins have been notified.';
        if ($result && $result['fellBack']) {
            $message .= ' '.$result['fellBack'].' file(s) could not be sent to Google Drive and were stored on the server instead.';
        }

        return $this->back($activity)->with('status', $message);
    }

    public function accept(Request $request, Activity $activity)
    {
        abort_unless($request->user()->canReview(), 403, 'Only TIF admins review WBS steps.');

        if (! $activity->isInReview()) {
            return $this->back($activity)->withErrors(['status' => 'This step is not waiting for review.'], 'status'.$activity->id);
        }

        $admin = $request->user();
        DB::transaction(function () use ($activity, $admin) {
            $activity->latestReview()?->update(['decision' => 'accepted', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
            $activity->update(['status' => 'done']);
        });

        Notification::send($this->managersOf($activity), new StepStatusChanged(StepStatusChanged::ACCEPTED, $activity, $admin));

        return $this->back($activity)->with('status', '"'.$activity->name.'" accepted and marked Done.');
    }

    public function reject(Request $request, Activity $activity)
    {
        abort_unless($request->user()->canReview(), 403, 'Only TIF admins review WBS steps.');

        if (! $activity->isInReview() && ! $activity->isDone()) {
            return $this->back($activity)->withErrors(['status' => 'Only a submitted or done step can be returned.'], 'status'.$activity->id);
        }

        $data = $request->validateWithBag('status'.$activity->id, [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'reason.required' => 'Write the reason, so the Project Manager knows what to revise.',
            'reason.min' => 'Write the reason, so the Project Manager knows what to revise.',
        ]);

        $admin = $request->user();
        $wasDone = $activity->isDone();
        DB::transaction(function () use ($activity, $admin, $data) {
            $review = $activity->latestReview();

            if ($review && $review->decision === null) {
                $review->update(['decision' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'reason' => $data['reason']]);
            } else {
                // Reopening a step that was already done: record it as a decision of its own.
                $review = $activity->reviews()->create([
                    'submitted_by' => $review?->submitted_by, 'submitted_at' => $review?->submitted_at ?? now(),
                    'progress_before' => $review?->progress_before ?? 90,
                    'decision' => 'rejected', 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'reason' => $data['reason'],
                ]);
            }

            $activity->update([
                'status' => 'in_progress',
                'progress' => min(99, $review->progress_before ?: 90),   // not finished until accepted
                'actual_finish' => null,
            ]);
        });

        $managers = $this->managersOf($activity);
        Notification::send($managers, new StepStatusChanged(StepStatusChanged::RETURNED, $activity, $admin, $data['reason']));
        [$emailed, $notEmailed] = $this->emailReturn($managers, $activity, $admin, $data['reason'], $wasDone);

        $message = '"'.$activity->name.'" returned to In Progress. The Project Manager has been notified in the app';
        $message .= $emailed ? ' and by email ('.implode(', ', $emailed).').' : '.';
        if ($notEmailed) {
            $message .= ' No email sent to '.implode(', ', $notEmailed).'.';
        }

        return $this->back($activity)->with('status', $message);
    }

    /**
     * Emails the return reason to each manager with an email address. A mail failure never undoes
     * the return: it is logged and the admin is told who was not emailed.
     *
     * @return array{0: list<string>, 1: list<string>} emailed names, and "name (why)" for the rest
     */
    private function emailReturn($managers, Activity $activity, User $admin, string $reason, bool $wasDone): array
    {
        $emailed = [];
        $notEmailed = [];

        foreach ($managers as $manager) {
            if (blank($manager->email)) {
                $notEmailed[] = $manager->name.' (no email address)';

                continue;
            }
            try {
                $manager->notify(new StepReturnedEmail($activity, $admin, $reason, $wasDone));
                $emailed[] = $manager->email;
            } catch (Throwable $e) {
                report($e);
                $notEmailed[] = $manager->name.' (the email could not be sent)';
            }
        }

        return [$emailed, $notEmailed];
    }

    private function authorizePartnerManager(Request $request, Activity $activity): void
    {
        abort_unless($activity->deliverable->project->accessibleBy($request->user()), 403);
        abort_unless($request->user()->canStartAndSubmit(), 403, 'Only the partner\'s Project Manager can start or submit a WBS step.');
    }

    /** The Project Managers of the step's partner, plus whoever submitted it. */
    private function managersOf(Activity $activity)
    {
        $partnerId = $activity->deliverable->project->partner_id;
        $submitter = $activity->reviews()->value('submitted_by');

        return User::where(fn ($q) => $q->where('partner_id', $partnerId)->where('partner_role', 'project_manager'))
            ->when($submitter, fn ($q) => $q->orWhere('id', $submitter))
            ->get();
    }

    private function back(Activity $activity)
    {
        return redirect()->to(route('deliverables.show', $activity->deliverable_id).'#act-'.$activity->id);
    }
}
