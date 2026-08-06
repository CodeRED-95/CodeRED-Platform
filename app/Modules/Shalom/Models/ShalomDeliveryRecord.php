<?php

declare(strict_types=1);

namespace App\Modules\Shalom\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShalomDeliveryRecord extends Model
{
    protected $table = 'shalom_delivery_records';

    protected $fillable = [
        'username',
        'field',
        'value',
        'timestamp',
        'sync_batch_id',
        'user_id',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
