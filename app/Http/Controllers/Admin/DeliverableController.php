<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DeliverableController extends Controller
{
    /**
     * Adds a deliverable to an existing project. Its WBS baseline (steps, weights, plan dates)
     * is copied from the project's first deliverable; progress starts at zero.
     */
    public function store(Request $request, Project $project)
    {
        $data = $request->validateWithBag('deliverable', [
            'name' => ['required', 'string', 'max:255', Rule::unique('deliverables', 'name')->where('project_id', $project->id)],
        ], [
            'name.unique' => 'This project already has a deliverable with that name.',
        ], ['name' => 'deliverable name']);

        $source = $project->deliverables()->with('activities')->first();
        abort_unless($source, 422, 'This project has no deliverable to copy the WBS from.');

        DB::transaction(function () use ($project, $data, $source) {
            $deliverable = $project->deliverables()->create([
                'name' => trim($data['name']),
                'sort_order' => (int) $project->deliverables()->max('sort_order') + 1,
            ]);

            foreach ($source->activities as $activity) {
                $deliverable->activities()->create($activity->only([
                    'phase', 'sort_order', 'name', 'weight', 'is_mandatory', 'circulation', 'plan_start', 'plan_finish', 'evidence_required',
                ]));
            }
        });

        return redirect()->route('projects.show', $project)
            ->with('status', 'Deliverable "'.$data['name'].'" added with the WBS baseline of "'.$source->name.'".');
    }

    public function destroy(Deliverable $deliverable)
    {
        $project = $deliverable->project;

        if ($project->deliverables()->count() <= 1) {
            return redirect()->route('projects.show', $project)
                ->withErrors(['delete' => 'A project needs at least one deliverable. Delete the project instead.']);
        }

        Storage::disk('local')->deleteDirectory('evidence/'.$project->id.'/'.$deliverable->id);
        $deliverable->delete();

        return redirect()->route('projects.show', $project)->with('status', 'Deliverable "'.$deliverable->name.'" deleted.');
    }
}
