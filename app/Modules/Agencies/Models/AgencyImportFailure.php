<?php

namespace App\Modules\Agencies\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyImportFailure extends Model
{
    public $timestamps = false;

    protected $fillable = ['agency_import_id', 'row_number', 'raw_data', 'errors', 'created_at'];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'errors' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
