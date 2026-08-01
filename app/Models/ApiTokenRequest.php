<?php

namespace App\Models;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenRequestType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiTokenRequest extends Model
{
    protected $fillable = [
        'request_uuid',
        'request_type',
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
        'requested_token_type',
        'token_type',
        'requested_abilities',
        'requested_expires_in_minutes',
        'requested_token_expires_in_days',
        'token_expires_in_days',
        'status',
        'requested_ip',
        'request_source',
        'idempotency_key',
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
        'source_personal_access_token_id',
        'replacement_personal_access_token_id',
        'encrypted_plain_text_token',
        'delivery_status',
        'delivered_at',
        'delivered_by',
        'delivery_channel',
        'delivered_to',
        'delivery_email',
        'delivery_telegram_username',
        'delivery_whatsapp_number',
        'delivery_email_masked',
        'delivery_telegram_username_masked',
        'delivery_whatsapp_number_masked',
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
            'delivery_email' => 'encrypted',
            'delivery_telegram_username' => 'encrypted',
            'delivery_whatsapp_number' => 'encrypted',
            'result_retrieved_at' => 'datetime',
            'status' => ApiTokenRequestStatus::class,
            'delivery_status' => ApiTokenRequestDeliveryStatus::class,
            'request_type' => ApiTokenRequestType::class,
        ];
    }

    public function requestTypeValue(): string
    {
        $type = $this->getAttribute('request_type');

        if ($type instanceof ApiTokenRequestType) {
            return $type->value;
        }

        return $type === null || $type === '' ? ApiTokenRequestType::Issuance->value : (string) $type;
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

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by')->withTrashed();
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'personal_access_token_id');
    }

    public function sourceToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'source_personal_access_token_id');
    }

    public function replacementToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'replacement_personal_access_token_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ApiTokenRequestEvent::class);
    }

    public function isDelivered(): bool
    {
        return $this->deliveryStatusValue() === ApiTokenRequestDeliveryStatus::Delivered->value || $this->delivered_at !== null;
    }

    public function canRevealDeliveryContact(User $user): bool
    {
        return ! $this->isDelivered() && $user->hasPermission('api-token-requests.view-delivery-contact');
    }

    /** @return array{email: string|null, telegram: string|null, whatsapp: string|null} */
    public function deliveryContact(): array
    {
        return [
            'email' => $this->stringOrNull($this->delivery_email),
            'telegram' => self::normalizeTelegram($this->stringOrNull($this->delivery_telegram_username)),
            'whatsapp' => self::normalizePhone($this->stringOrNull($this->delivery_whatsapp_number)),
        ];
    }

    /** @return array{email: string|null, telegram: string|null, whatsapp: string|null} */
    public function maskedDeliveryContact(): array
    {
        $contact = $this->deliveryContact();

        return [
            'email' => $this->delivery_email_masked ?: ($contact['email'] === null ? null : self::maskEmail($contact['email'])),
            'telegram' => $this->delivery_telegram_username_masked ?: ($contact['telegram'] === null ? null : self::maskTelegram($contact['telegram'])),
            'whatsapp' => $this->delivery_whatsapp_number_masked ?: ($contact['whatsapp'] === null ? null : self::maskPhone($contact['whatsapp'])),
        ];
    }

    /** @return array{email: string|null, telegram: string|null, whatsapp: string|null} */
    public static function maskedContactFromValues(?string $email, ?string $telegram, ?string $whatsapp): array
    {
        return [
            'email' => $email === null || trim($email) === '' ? null : self::maskEmail($email),
            'telegram' => $telegram === null || trim($telegram) === '' ? null : self::maskTelegram($telegram),
            'whatsapp' => $whatsapp === null || trim($whatsapp) === '' ? null : self::maskPhone($whatsapp),
        ];
    }

    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', trim($email), 2), 2, '');
        if ($domain === '') {
            return '***';
        }

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    public static function maskTelegram(string $username): string
    {
        $value = '@'.ltrim(trim($username), '@');
        $length = mb_strlen($value);
        if ($length <= 3) {
            return '@***';
        }

        return mb_substr($value, 0, 2).str_repeat('*', max(3, $length - 3)).mb_substr($value, -1);
    }

    public static function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if ($digits === '') {
            return '***';
        }

        $prefix = Str::startsWith($digits, '51') ? '+51 ' : '+';

        return $prefix.'******'.substr($digits, -3);
    }

    public static function normalizeTelegram(?string $username): ?string
    {
        if ($username === null || trim($username) === '') {
            return null;
        }

        return '@'.ltrim(trim($username), '@');
    }

    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return $digits === '' ? null : '+'.$digits;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
