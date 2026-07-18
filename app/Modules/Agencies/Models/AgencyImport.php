<?php

namespace App\Modules\Agencies\Models;

use App\Modules\Agencies\Enums\AgencyImportStatus;
use Illuminate\Database\Eloquent\Model;

class AgencyImport extends Model
{
    protected $fillable = [
        'user_id', 'original_filename', 'stored_filename', 'file_type', 'status', 'strategy',
        'total_rows', 'valid_rows', 'imported_rows', 'updated_rows', 'skipped_rows', 'failed_rows',
        'started_at', 'completed_at', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'status' => AgencyImportStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
