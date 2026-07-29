<?php

namespace App\Models;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ApiTokenRequest extends Model
{
    protected $fillable = [
        'request_uuid',
        'requester_name',
        'requester_email',
        'requester_phone',
        'application_name',
        'purpose',
        'telegram_user_id',
        'telegram_chat_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'requested_token_name',
        'requested_abilities',
        'requested_expires_in_minutes',
        'status',
        'requested_ip',
        'request_source',
        'metadata',
        'requested_at',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'reviewed_by',
        'rejection_reason',
        'cancellation_reason',
        'personal_access_token_id',
        'encrypted_plain_text_token',
        'delivery_status',
        'delivered_at',
        'delivery_channel',
        'delivered_to',
        'delivery_metadata',
        'delivery_attempts',
        'delivery_reference',
        'result_retrieved_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_abilities' => 'array',
            'metadata' => 'array',
            'delivery_metadata' => 'array',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivered_at' => 'datetime',
            'result_retrieved_at' => 'datetime',
            'status' => ApiTokenRequestStatus::class,
            'delivery_status' => ApiTokenRequestDeliveryStatus::class,
        ];
    }

    public function statusValue(): string
    {
        $status = $this->getAttribute('status');

        return $status instanceof ApiTokenRequestStatus ? $status->value : (string) $status;
    }

    public function deliveryStatusValue(): string
    {
        $status = $this->getAttribute('delivery_status');

        return $status instanceof ApiTokenRequestDeliveryStatus ? $status->value : (string) $status;
    }

    public function requestedAt(): ?Carbon
    {
        $value = $this->getAttribute('requested_at');

        return $value === null ? null : $this->asDateTime($value);
    }

    public function reviewedAt(): ?Carbon
    {
        $value = $this->getAttribute('reviewed_at');

        return $value === null ? null : $this->asDateTime($value);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'personal_access_token_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApiTokenRequestEvent::class);
    }
}
