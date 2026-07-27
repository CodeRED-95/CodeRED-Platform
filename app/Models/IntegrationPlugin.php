<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationPlugin extends Model
{
    protected $fillable = ['integration_id', 'plugin_id', 'name', 'version', 'enabled', 'metadata', 'last_seen'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'metadata' => 'array', 'last_seen' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
