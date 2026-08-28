<?php

namespace App\Services\Anime\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderCache extends Model
{
    protected $table = 'provider_cache';

    protected $fillable = [
        'provider',
        'bucket',
        'cache_key',
        'payload',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
