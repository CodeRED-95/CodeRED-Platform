<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiTokenRequestEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'api_token_request_id',
        'event',
        'description',
        'metadata',
        'performed_by',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ApiTokenRequest::class, 'api_token_request_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }

    public static function logPublicStatusCheck(bool $found, string $trackingCode, string $outcome, ?int $requestId = null): void
    {
        self::query()->create([
            'api_token_request_id' => $requestId,
            'event' => $found ? 'public_status_checked' : 'public_status_check_failed',
            'description' => 'Consulta pública de estado: '.$outcome,
            'metadata' => [
                'tracking_code_used' => $trackingCode,
                'outcome' => $outcome,
            ],
            'ip_address' => hash('sha256', (string) request()->ip()),
            'user_agent' => hash('sha256', (string) request()->userAgent()),
            'created_at' => now(),
        ]);
    }
}
