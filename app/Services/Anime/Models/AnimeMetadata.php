<?php

namespace App\Services\Anime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnimeMetadata extends Model
{
    protected $table = 'anime_metadata';

    protected $fillable = [
        'anime_id',
        'provider',
        'external_id',
        'titles',
        'synonyms',
        'genres',
        'studios',
        'relations',
        'characters',
        'payload',
        'synced_at',
    ];

    protected $casts = [
        'anime_id' => 'integer',
        'titles' => 'array',
        'synonyms' => 'array',
        'genres' => 'array',
        'studios' => 'array',
        'relations' => 'array',
        'characters' => 'array',
        'payload' => 'array',
        'synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function anime(): BelongsTo
    {
        return $this->belongsTo(AnimeRecord::class, 'anime_id');
    }
}
