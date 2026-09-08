<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_SECURITY_ALERT = 'security_alert';

    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const DELIVERED = 'delivered';
    public const BOUNCED = 'bounced';
    public const COMPLAINED = 'complained';
    public const FAILED = 'failed';

    protected $fillable = [
        'user_id', 'recipient', 'type', 'provider', 'provider_message_id',
        'status', 'queued_at', 'sent_at', 'delivered_at', 'failed_at',
        'failure_category', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
