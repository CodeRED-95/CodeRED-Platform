<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationService extends Model
{
    protected $fillable = ['integration_id', 'service', 'enabled', 'metadata', 'last_seen'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'metadata' => 'array', 'last_seen' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
