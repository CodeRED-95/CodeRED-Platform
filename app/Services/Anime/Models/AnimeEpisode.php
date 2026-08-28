<?php

namespace App\Services\Anime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnimeEpisode extends Model
{
    protected $table = 'episodes';

    protected $fillable = [
        'anime_id',
        'season_id',
        'number',
        'title',
        'description',
        'language',
        'poster_url',
        'aired_at',
        'duration_seconds',
    ];

    protected $casts = [
        'anime_id' => 'integer',
        'season_id' => 'integer',
        'number' => 'integer',
        'aired_at' => 'datetime',
        'duration_seconds' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function anime(): BelongsTo
    {
        return $this->belongsTo(AnimeRecord::class, 'anime_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(AnimeSeason::class, 'season_id');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(EpisodeServer::class, 'episode_id')->orderBy('priority')->orderBy('id');
    }
}
