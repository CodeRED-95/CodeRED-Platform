<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'destination',
        'status',
        'attempts',
        'last_status_code',
        'delivered_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_status_code' => 'integer',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
