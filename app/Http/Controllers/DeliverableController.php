<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use Illuminate\Http\Request;

class DeliverableController extends Controller
{
    /** The WBS of one deliverable: progress updates and evidence per step. */
    public function show(Request $request, Deliverable $deliverable)
    {
        abort_unless($deliverable->project->accessibleBy($request->user()), 403);

        $deliverable->load(['project.partner', 'activities.evidences.uploader', 'activities.updater', 'activities.reviews.submitter', 'activities.reviews.reviewer']);

        return view('deliverables.show', compact('deliverable'));
    }
}
