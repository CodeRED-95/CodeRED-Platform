<?php

namespace App\Services\Anime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimeExternalId extends Model
{
    protected $table = 'anime_external_ids';

    protected $fillable = [
        'anime_id',
        'provider',
        'external_id',
        'external_slug',
    ];

    protected $casts = [
        'anime_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function anime(): BelongsTo
    {
        return $this->belongsTo(AnimeRecord::class, 'anime_id');
    }
}
