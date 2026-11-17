<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /** Admins see every project; partner staff see only their company's projects. */
    public function index(Request $request)
    {
        $user = $request->user();

        $projects = Project::with(['deliverables.activities', 'partner'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('partner_id', $user->partner_id ?? 0))
            ->orderBy('name')
            ->get();

        return view('projects.index', compact('projects'));
    }

    /** Project overview: overall status plus one row per deliverable. */
    public function show(Request $request, Project $project)
    {
        abort_unless($project->accessibleBy($request->user()), 403);

        $project->load(['partner', 'deliverables.activities.evidences']);

        return view('projects.show', compact('project'));
    }
}
