<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailWebhookEvent extends Model
{
    protected $fillable = [
        'provider_event_id', 'event_type', 'provider_message_id', 'received_at',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }
}
