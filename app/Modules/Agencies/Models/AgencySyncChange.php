<?php

namespace App\Modules\Agencies\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $payload
 * @property CarbonImmutable|null $changed_at
 */
class AgencySyncChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'agency_internal_id', 'external_id', 'code', 'operation', 'payload', 'schema_version', 'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'agency_internal_id' => 'integer',
            'external_id' => 'integer',
            'payload' => 'array',
            'schema_version' => 'integer',
            'changed_at' => 'immutable_datetime',
        ];
    }
}
