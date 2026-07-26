<?php

namespace App\Modules\Agencies\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgencyImportRun extends Model
{
    protected $fillable = [
        'type',
        'status',
        'progress',
        'stage',
        'chosen_original_name',
        'chosen_storage_path',
        'extracted_json_path',
        'report_json_path',
        'started_at',
        'finished_at',
        'total_received',
        'total_processed',
        'new_count',
        'updated_count',
        'renamed_count',
        'unchanged_count',
        'conflict_count',
        'missing_count',
        'error_count',
        'error_message',
        'created_by',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return HasMany<AgencyImportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(AgencyImportItem::class, 'import_run_id');
    }
}
