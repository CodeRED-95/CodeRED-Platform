<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShalomRecordarRecord extends Model
{
    protected $table = 'shalom_recordar_records';

    protected $fillable = [
        'user_id',
        'installation_id',
        'installation_uuid',
        'external_record_id',
        'record_hash',
        'field',
        'value',
        'recorded_at',
        'sync_batch_id',
        'sync_cursor',
        'payload',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(ShalomRecordarInstallation::class, 'installation_id');
    }
}
