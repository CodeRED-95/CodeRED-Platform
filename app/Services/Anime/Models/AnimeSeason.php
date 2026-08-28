<?php

namespace App\Services\Anime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimeSeason extends Model
{
    protected $table = 'seasons';

    protected $fillable = [
        'anime_id',
        'number',
        'title',
        'year',
        'status',
    ];

    protected $casts = [
        'anime_id' => 'integer',
        'number' => 'integer',
        'year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function anime(): BelongsTo
    {
        return $this->belongsTo(AnimeRecord::class, 'anime_id');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(AnimeEpisode::class, 'season_id')->orderBy('number');
    }
}
