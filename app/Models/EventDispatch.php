<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventDispatch extends Model
{
    use HasFactory;

    protected $table = 'event_dispatches';

    protected $fillable = [
        'event_id',
        'type',
        'status',
        'attempts',
        'response_code',
        'response_body',
        'error',
        'payload',
        'occurred_at',
        'tenant',
        'source',
        'duration_ms',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_code' => 'integer',
        'attempts' => 'integer',
        'duration_ms' => 'integer',
        'occurred_at' => 'datetime:UTC',
    ];
}
