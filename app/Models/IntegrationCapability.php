<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationCapability extends Model
{
    protected $fillable = ['integration_id', 'capability', 'service', 'method', 'path', 'version', 'enabled', 'last_seen', 'checksum'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_seen' => 'datetime'];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
