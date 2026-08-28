<?php

namespace App\Services\Anime\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeServer extends Model
{
    protected $table = 'episode_servers';

    protected $fillable = [
        'episode_id',
        'provider',
        'server_id',
        'name',
        'type',
        'language',
        'url',
        'priority',
        'status',
        'last_checked_at',
    ];

    protected $casts = [
        'episode_id' => 'integer',
        'priority' => 'integer',
        'last_checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(AnimeEpisode::class, 'episode_id');
    }
}
