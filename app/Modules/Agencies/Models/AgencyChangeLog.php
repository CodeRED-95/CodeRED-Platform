<?php

namespace App\Modules\Agencies\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyChangeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'agency_id', 'user_id', 'action', 'old_values', 'new_values', 'changed_fields', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
