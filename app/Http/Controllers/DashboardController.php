<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Evidence;
use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use App\Services\GoogleDrive;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The main page. Answers "what needs my attention today?" for the projects the user can see:
 * every project for TIF admins, their own company's projects for partner staff.
 */
class DashboardController extends Controller
{
    private const UPCOMING_DAYS = 14;

    private const LIST_LIMIT = 8;

    public function __invoke(Request $request, GoogleDrive $drive)
    {
        $user = $request->user();
        $today = today();

        $projects = Project::with(['partner', 'deliverables.activities.evidences', 'deliverables.activities.updater', 'deliverables.activities.reviews.submitter', 'deliverables.activities.reviews.reviewer'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('partner_id', $user->partner_id ?? 0))
            ->orderBy('name')
            ->get();

        // Every step with the deliverable and project it belongs to, so lists can link back.
        $steps = $projects->flatMap(fn (Project $p) => $p->deliverables->flatMap(
            fn ($d) => $d->activities->map(fn (Activity $a) => ['activity' => $a, 'deliverable' => $d, 'project' => $p, 'status' => $a->schedule()])
        ));

        $overdue = $steps->where('status', 'late')
            ->sortBy(fn ($s) => $s['activity']->plan_finish->getTimestamp())->values();

        $behind = $steps->where('status', 'behind')
            ->sortBy(fn ($s) => $s['activity']->plan_finish->getTimestamp())->values();

        $upcoming = $steps->filter(fn ($s) => in_array($s['status'], ['on_track', 'behind', 'upcoming'], true)
                && $s['activity']->plan_finish->betweenIncluded($today, $today->copy()->addDays(self::UPCOMING_DAYS)))
            ->sortBy(fn ($s) => $s['activity']->plan_finish->getTimestamp())->values();

        $missingEvidence = $steps->filter(fn ($s) => $s['activity']->isDone() && $s['activity']->evidences->isEmpty())->values();

        // Submitted steps waiting for a TIF admin, oldest submission first.
        $reviewQueue = $steps->filter(fn ($s) => $s['activity']->isInReview())
            ->sortBy(fn ($s) => $s['activity']->latestReview()?->submitted_at?->getTimestamp() ?? 0)->values();

        // Steps a TIF admin sent back, newest first.
        $returned = $steps->filter(fn ($s) => $s['activity']->returnedReview() !== null)
            ->sortByDesc(fn ($s) => $s['activity']->returnedReview()->reviewed_at->getTimestamp())->values();

        // Plan start has passed but nobody pressed Start.
        $notStarted = $steps->filter(fn ($s) => $s['activity']->shouldHaveStarted() && $s['status'] !== 'late')->count();

        $deliverables = $projects->flatMap->deliverables;
        $statusCounts = $deliverables->countBy(fn ($d) => $d->status());

        return view('dashboard', [
            'user' => $user,
            'today' => $today,
            'projects' => $projects->sortBy(fn (Project $p) => $p->deviation())->values(),   // worst first
            'stats' => [
                'projects' => $projects->count(),
                'deliverables' => $deliverables->count(),
                'lagged' => $statusCounts->get('lagged', 0),
                'on_schedule' => $statusCounts->get('on_schedule', 0),
                'completed' => $statusCounts->get('completed', 0),
                'not_started' => $statusCounts->get('not_started', 0) + $statusCounts->get('empty', 0),
                'overdue' => $overdue->count(),
                'behind' => $behind->count(),
                'missing_evidence' => $missingEvidence->count(),
                'in_review' => $reviewQueue->count(),
                'returned' => $returned->count(),
                'not_started_steps' => $notStarted,
                'plan' => round((float) $projects->avg(fn (Project $p) => $p->planPercent()), 1),
                'actual' => round((float) $projects->avg(fn (Project $p) => $p->actualPercent()), 1),
            ],
            'overdue' => $overdue->take(self::LIST_LIMIT),
            'upcoming' => $upcoming->take(self::LIST_LIMIT),
            'upcomingCount' => $upcoming->count(),
            'missingEvidence' => $missingEvidence->take(self::LIST_LIMIT),
            'reviewQueue' => $reviewQueue->take(self::LIST_LIMIT),
            'returned' => $returned->take(self::LIST_LIMIT),
            'recent' => $this->recentActivity($steps),
            'upcomingDays' => self::UPCOMING_DAYS,
            'admin' => $user->isAdmin() ? $this->adminNotes($drive) : null,
        ]);
    }

    /**
     * Latest progress updates and evidence uploads, newest first.
     *
     * @param  Collection<int, array<string, mixed>>  $steps
     * @return Collection<int, array<string, mixed>>
     */
    private function recentActivity(Collection $steps): Collection
    {
        $updates = $steps->filter(fn ($s) => $s['activity']->updated_by !== null)->map(fn ($s) => [
            'type' => 'progress',
            'at' => $s['activity']->updated_at,
            'who' => $s['activity']->updater,
            'text' => 'set progress to '.$s['activity']->progress.'%',
        ] + $s);

        $uploads = $steps->flatMap(fn ($s) => $s['activity']->evidences->map(fn (Evidence $e) => [
            'type' => 'evidence',
            'at' => $e->created_at,
            'who' => null,
            'uploader_id' => $e->uploaded_by,
            'text' => 'uploaded '.$e->original_name,
        ] + $s));

        $recent = $updates->concat($uploads)->sortByDesc(fn ($r) => $r['at']->getTimestamp())->take(self::LIST_LIMIT)->values();

        // Uploader names for the few rows shown (evidence rows don't have the user loaded).
        $names = User::whereIn('id', $recent->pluck('uploader_id')->filter()->unique())->get()->keyBy('id');

        return $recent->map(function ($r) use ($names) {
            $r['who'] ??= $names->get($r['uploader_id'] ?? null);

            return $r;
        });
    }

    /** Housekeeping only admins can act on. */
    private function adminNotes(GoogleDrive $drive): array
    {
        return [
            'partners' => Partner::count(),
            'partnersWithoutAccounts' => Partner::doesntHave('users')->orderBy('name')->get(),
            'awaitingFirstSignIn' => User::where('role', 'partner')->where('must_change_password', true)->with('partner')->orderBy('name')->get(),
            'driveConfigured' => $drive->configured(),
            'driveConnected' => $drive->connected(),
        ];
    }
}
