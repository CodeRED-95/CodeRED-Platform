<?php

namespace App\Modules\Agencies\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property list<string>|null $changed_fields
 */
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

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
