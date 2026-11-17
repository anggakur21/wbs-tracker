<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Project;
use App\Services\WbsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectController extends Controller
{
    public function create()
    {
        return view('admin.projects.create', [
            'partners' => Partner::withCount('users')->orderBy('name')->get(),
            'template' => WbsTemplate::items(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contract_number' => ['nullable', 'string', 'max:255'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'partner_id' => ['required', Rule::exists('partners', 'id')],
            'deliverables' => ['required', 'array', 'min:1', 'max:50'],
            'deliverables.*' => ['required', 'string', 'max:255', 'distinct'],
            'plan_mode' => ['required', Rule::in(['manual', 'excel'])],
        ], [
            'deliverables.required' => 'Add at least one deliverable.',
            'deliverables.*.required' => 'Every deliverable needs a name.',
            'deliverables.*.distinct' => 'Deliverable names must be different from each other.',
        ], [
            'name' => 'project name',
            'partner_id' => 'implementation partner',
            'deliverables.*' => 'deliverable name',
        ]);

        // Manual: every deliverable has its own WBS tab (dates, weights, optional steps).
        // Excel: one uploaded WBS is used for every deliverable.
        if ($data['plan_mode'] === 'excel') {
            $wbs = $this->wbsFromExcel($request);
            $plans = array_map(fn () => $wbs, $data['deliverables']);
        } else {
            $plans = $this->wbsFromManual($request, $data['deliverables']);
        }

        $project = DB::transaction(function () use ($data, $plans, $request) {
            $project = Project::create([
                'name' => $data['name'],
                'contract_number' => $data['contract_number'] ?? null,
                'contract_value' => $data['contract_value'] ?? null,
                'partner_id' => $data['partner_id'],
                'created_by' => $request->user()->id,
            ]);

            $order = 0;
            foreach ($data['deliverables'] as $key => $deliverableName) {
                $deliverable = $project->deliverables()->create(['name' => trim($deliverableName), 'sort_order' => ++$order]);

                foreach (array_values($plans[$key]) as $i => $row) {
                    $deliverable->activities()->create($row + ['sort_order' => $i + 1]);
                }
            }

            return $project;
        });

        $message = 'Project "'.$project->name.'" created with '.count($data['deliverables']).' deliverable(s).';
        $offWeight = collect($plans)->map(fn ($rows) => array_sum(array_column($rows, 'weight')))
            ->filter(fn ($total) => abs($total - 100) > 0.01);
        if ($offWeight->isNotEmpty()) {
            $message .= ' '.$offWeight->count().' deliverable(s) have a total weight other than 100; their percentages are calculated proportionally.';
        }

        return redirect()->route('projects.show', $project)->with('status', $message);
    }

    public function destroy(Project $project)
    {
        Storage::disk('local')->deleteDirectory('evidence/'.$project->id);
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project "'.$project->name.'" deleted.');
    }

    /**
     * Manual mode: each deliverable tab lists the configured WBS items. Mandatory items are always
     * included; optional items only when ticked. The admin types weights and plan dates per tab.
     *
     * @param  array<int|string, string>  $deliverables
     * @return array<int|string, list<array<string, mixed>>>
     */
    private function wbsFromManual(Request $request, array $deliverables): array
    {
        $items = WbsTemplate::items();
        $plans = [];
        $errors = [];

        foreach ($deliverables as $key => $deliverableName) {
            $label = trim($deliverableName) ?: 'Deliverable '.$key;
            $rows = [];

            foreach ($items as $n => $item) {
                $input = (array) $request->input("plans.$key.{$item->id}", []);
                $included = $item->is_mandatory || ! empty($input['include']);
                if (! $included) {
                    continue;
                }

                $where = $label.', step '.($n + 1).' "'.$item->name.'"';
                $weight = $input['weight'] ?? null;
                $start = $input['plan_start'] ?? null;
                $finish = $input['plan_finish'] ?? null;

                if (! is_numeric($weight) || $weight < 0 || $weight > 100) {
                    $errors["plans.$key.{$item->id}.weight"] = $where.': weight must be a number from 0 to 100.';
                }
                if (! $this->isDate($start)) {
                    $errors["plans.$key.{$item->id}.plan_start"] = $where.': plan start is required.';
                }
                if (! $this->isDate($finish)) {
                    $errors["plans.$key.{$item->id}.plan_finish"] = $where.': plan finish is required.';
                } elseif ($this->isDate($start) && $finish < $start) {
                    $errors["plans.$key.{$item->id}.plan_finish"] = $where.': plan finish cannot be earlier than plan start.';
                }

                $rows[] = [
                    'phase' => $item->phase,
                    'name' => $item->name,
                    'weight' => (float) $weight,
                    'is_mandatory' => $item->is_mandatory,
                    'circulation' => $item->circulation,
                    'plan_start' => $start,
                    'plan_finish' => $finish,
                    'evidence_required' => $item->evidence_required,
                ];
            }

            if (! $rows) {
                $errors["plans.$key"] = $label.': include at least one WBS step.';
            }
            $plans[$key] = $rows;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $plans;
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }

    /** Excel mode: the whole WBS (phases, activities, weights, plan dates) is read from the upload. */
    private function wbsFromExcel(Request $request): array
    {
        $request->validate([
            'wbs_file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ], [
            'wbs_file.required' => 'Upload the WBS template (Excel) file.',
            'wbs_file.mimes' => 'The WBS file must be .xlsx or .xls.',
            'wbs_file.max' => 'The WBS file can be at most 5 MB.',
        ]);

        try {
            $rows = WbsTemplate::fromExcel($request->file('wbs_file')->getRealPath());
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['wbs_file' => $e->getMessage()]);
        }

        // Steps that match a configured WBS item by name keep its mandatory/optional setting.
        $optional = WbsTemplate::items()->reject->is_mandatory->map(fn ($i) => mb_strtolower(trim($i->name)))->all();

        return array_map(fn ($row) => $row + ['is_mandatory' => ! in_array(mb_strtolower(trim($row['name'])), $optional, true)], $rows);
    }
}
