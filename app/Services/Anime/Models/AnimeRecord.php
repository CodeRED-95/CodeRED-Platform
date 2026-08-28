<?php

namespace App\Services\Anime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnimeRecord extends Model
{
    protected $table = 'anime';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'poster_url',
        'banner_url',
        'year',
        'status',
    ];

    protected $casts = [
        'year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function externalIds(): HasMany
    {
        return $this->hasMany(AnimeExternalId::class, 'anime_id');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(AnimeSeason::class, 'anime_id')->orderBy('number');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(AnimeEpisode::class, 'anime_id')->orderBy('number');
    }

    public function metadata(): HasOne
    {
        return $this->hasOne(AnimeMetadata::class, 'anime_id');
    }
}
