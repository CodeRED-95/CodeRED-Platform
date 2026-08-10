<?php

namespace App\Models;

use App\Enums\TokenRequestAuditAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenRequestAuditLog extends Model
{
    /**
     * La tabla la crea 2026_08_05_100001_create_api_token_request_audit_logs_table;
     * sin declararla, Eloquent inferiría `token_request_audit_logs`, que no existe.
     */
    protected $table = 'api_token_request_audit_logs';

    protected $fillable = [
        'api_token_request_id',
        'user_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'user_agent_hash',
        'status_before',
        'status_after',
        'sensitive_data_revealed',
        'sensitive_data_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'action' => TokenRequestAuditAction::class,
            'details' => 'array',
            'sensitive_data_revealed' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function apiTokenRequest(): BelongsTo
    {
        return $this->belongsTo(ApiTokenRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Crea un log de auditoría de forma segura
     * Nunca guarda datos sensibles en plaintext
     */
    public static function logAction(
        ApiTokenRequest $request,
        TokenRequestAuditAction $action,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $details = null,
    ): self {
        return self::create([
            'api_token_request_id' => $request->id,
            'user_id' => $user?->id,
            'action' => $action,
            'details' => $details ?? [],
            'ip_address' => $ip ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'user_agent_hash' => hash('sha256', $userAgent ?? request()->userAgent()),
            'sensitive_data_revealed' => $action->isSensitive(),
            'sensitive_data_type' => $action->isSensitive() ? $action->value : null,
        ]);
    }

    /**
     * Obtiene todos los logs de una solicitud
     */
    public static function getForRequest(ApiTokenRequest $request)
    {
        return self::where('api_token_request_id', $request->id)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Obtiene los logs sensibles de una solicitud
     */
    public static function getSensitiveLogsForRequest(ApiTokenRequest $request)
    {
        return self::where('api_token_request_id', $request->id)
            ->where('sensitive_data_revealed', true)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Verifica si un usuario ha visto los datos protegidos de una solicitud
     */
    public static function hasUserViewedProtectedData(ApiTokenRequest $request, User $user): bool
    {
        return self::where('api_token_request_id', $request->id)
            ->where('user_id', $user->id)
            ->where('action', TokenRequestAuditAction::ProtectedDataViewed)
            ->exists();
    }

    /**
     * Verifica si un usuario ha revelado el token
     */
    public static function hasUserRevealedToken(ApiTokenRequest $request, User $user): bool
    {
        return self::where('api_token_request_id', $request->id)
            ->where('user_id', $user->id)
            ->where('action', TokenRequestAuditAction::TokenRevealed)
            ->exists();
    }

    /**
     * Obtiene la fecha y usuario que entregó el token
     */
    public static function getDeliveryInfo(ApiTokenRequest $request): ?self
    {
        return self::where('api_token_request_id', $request->id)
            ->where('action', TokenRequestAuditAction::DeliveryConfirmed)
            ->latest('created_at')
            ->first();
    }
}
