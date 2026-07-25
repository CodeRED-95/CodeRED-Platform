<?php

namespace App\Modules\Agencies\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgencyImportItem extends Model
{
    protected $fillable = [
        'import_run_id',
        'external_id',
        'matched_agency_id',
        'action',
        'confidence',
        'incoming_data',
        'current_data',
        'differences',
        'proposed_old_name',
        'conflict_reason',
        'selected',
    ];

    protected function casts(): array
    {
        return [
            'incoming_data' => 'array',
            'current_data' => 'array',
            'differences' => 'array',
            'selected' => 'boolean',
        ];
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(AgencyImportRun::class, 'import_run_id');
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'matched_agency_id');
    }
}
