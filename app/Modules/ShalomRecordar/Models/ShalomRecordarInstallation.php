<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShalomRecordarInstallation extends Model
{
    protected $table = 'shalom_recordar_installations';

    protected $fillable = [
        'user_id',
        'installation_uuid',
        'extension_version',
        'device_name',
        'browser_name',
        'browser_version',
        'platform_name',
        'platform_version',
        'last_synced_at',
        'last_sync_cursor',
        'last_sync_hash',
        'last_seen_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(ShalomRecordarRecord::class, 'installation_id');
    }
}
