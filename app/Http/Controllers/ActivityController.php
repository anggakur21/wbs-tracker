<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    /**
     * Progress and notes, by partner staff (Project Manager, Project Admin, Business Analyst) or an admin,
     * while the step is In Progress. Starting and submitting are separate actions (ActivityStatusController).
     */
    public function update(Request $request, Activity $activity)
    {
        abort_unless($activity->deliverable->project->accessibleBy($request->user()), 403);
        abort_unless($request->user()->canUpdateWbs(), 403, 'Your account role cannot update the WBS.');

        if (! $activity->isEditable()) {
            return $this->backToActivity($activity)
                ->withErrors(['progress' => 'Progress can only be updated while the step is In Progress.'], 'activity'.$activity->id);
        }

        $data = $request->validateWithBag('activity'.$activity->id, [
            'progress' => ['required', 'integer', 'between:0,99'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'progress.between' => 'Progress can be 0 to 99%. To finish the step, submit it for review with the evidence.',
        ]);

        $activity->update($data + ['updated_by' => $request->user()->id]);

        return $this->backToActivity($activity)->with('status', 'Progress for "'.$activity->name.'" saved.');
    }

    /** Baseline change (weight and plan dates). TIF admins only; the route is also admin-gated. */
    public function updatePlan(Request $request, Activity $activity)
    {
        abort_unless($request->user()->canEditBaseline(), 403, 'Only TIF admins can change the baseline.');

        $data = $request->validateWithBag('plan'.$activity->id, [
            'weight' => ['required', 'numeric', 'between:0,100'],
            'plan_start' => ['required', 'date'],
            'plan_finish' => ['required', 'date', 'after_or_equal:plan_start'],
        ]);

        $activity->update($data);

        return $this->backToActivity($activity)->with('status', 'Baseline for "'.$activity->name.'" saved.');
    }

    private function backToActivity(Activity $activity)
    {
        return redirect()->to(route('deliverables.show', $activity->deliverable_id).'#act-'.$activity->id);
    }
}
