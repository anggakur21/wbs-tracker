<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One step of the standard WBS that new deliverables start from. Configured by TIF admins.
 * Mandatory steps are always included; optional steps can be left out per deliverable.
 */
class WbsItem extends Model
{
    protected $fillable = [
        'sort_order', 'phase', 'name', 'weight', 'is_mandatory',
        'circulation', 'evidence_required', 'offset_start', 'offset_finish',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'is_mandatory' => 'boolean',
            'offset_start' => 'integer',
            'offset_finish' => 'integer',
        ];
    }
}
