<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WbsItem;
use App\Services\WbsTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The standard WBS that new deliverables start from. Changes apply to projects created afterwards;
 * existing deliverables keep the steps they were created with.
 */
class WbsItemController extends Controller
{
    public function index()
    {
        return view('admin.wbs-items', ['items' => WbsTemplate::items()]);
    }

    /** Saves the whole list at once: edits, new rows, removed rows and the order. */
    public function update(Request $request)
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.phase' => ['required', 'string', 'max:255'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.weight' => ['required', 'numeric', 'between:0,100'],
            'items.*.is_mandatory' => ['required', 'boolean'],
            'items.*.offset_start' => ['required', 'integer', 'min:0', 'max:3650'],
            'items.*.offset_finish' => ['required', 'integer', 'min:0', 'max:3650', 'gte:items.*.offset_start'],
            'items.*.circulation' => ['nullable', 'string', 'max:2000'],
            'items.*.evidence_required' => ['nullable', 'string', 'max:2000'],
        ], [
            'items.required' => 'Keep at least one WBS item.',
            'items.*.phase.required' => 'Every item needs a phase.',
            'items.*.name.required' => 'Every item needs a name.',
            'items.*.weight.*' => 'Weight must be a number from 0 to 100.',
            'items.*.offset_finish.gte' => 'Finish day cannot be before the start day.',
        ]);

        $rows = array_values($request->input('items'));
        $keepIds = array_filter(array_map(fn ($r) => (int) ($r['id'] ?? 0), $rows));
        if (count($keepIds) !== count(array_unique($keepIds))) {
            throw ValidationException::withMessages(['items' => 'The list was submitted twice. Reload the page and try again.']);
        }

        DB::transaction(function () use ($rows, $keepIds) {
            WbsItem::whereNotIn('id', $keepIds ?: [0])->delete();

            foreach ($rows as $i => $row) {
                $data = [
                    'sort_order' => $i + 1,
                    'phase' => trim($row['phase']),
                    'name' => trim($row['name']),
                    'weight' => (float) $row['weight'],
                    'is_mandatory' => (bool) $row['is_mandatory'],
                    'offset_start' => (int) $row['offset_start'],
                    'offset_finish' => (int) $row['offset_finish'],
                    'circulation' => trim((string) ($row['circulation'] ?? '')) ?: null,
                    'evidence_required' => trim((string) ($row['evidence_required'] ?? '')) ?: null,
                ];

                $item = ! empty($row['id']) ? WbsItem::find($row['id']) : null;
                $item ? $item->update($data) : WbsItem::create($data);
            }
        });

        $total = array_sum(array_map(fn ($r) => (float) $r['weight'], $rows));
        $message = 'WBS items saved ('.count($rows).' items). New projects will use them.';
        if (abs($total - 100) > 0.01) {
            $message .= ' Total weight is '.round($total, 2).', not 100; percentages are calculated proportionally.';
        }

        return redirect()->route('admin.wbs-items.index')->with('status', $message);
    }
}
