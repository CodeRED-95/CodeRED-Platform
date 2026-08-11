<?php

namespace App\Services\ApiTokens;

use App\Enums\TokenRequestAuditAction;
use App\Models\ApiTokenRequest;
use App\Models\TokenRequestAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Registra una acción de auditoría
     */
    public static function log(
        ApiTokenRequest $request,
        TokenRequestAuditAction $action,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $details = null,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
    ): TokenRequestAuditLog {
        // Usar valores por defecto del request si no se proporcionan
        $ip = $ip ?? Request::ip();
        $userAgent = $userAgent ?? Request::userAgent();

        return TokenRequestAuditLog::logAction(
            $request,
            $action,
            $user,
            $ip,
            $userAgent,
            $details,
        );
    }

    /**
     * Registra la visualización de datos protegidos
     */
    public static function logProtectedDataView(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::ProtectedDataViewed,
            $user,
            $ip,
            $userAgent,
            details: [
                'fields_revealed' => [
                    'requester_name',
                    'requester_phone',
                    'purpose',
                    'delivery_method',
                ],
            ],
        );
    }

    /**
     * Registra la revelación de token
     */
    public static function logTokenRevealed(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::TokenRevealed,
            $user,
            $ip,
            $userAgent,
            details: [
                'reveal_count' => $request->getTokenRevealCount() + 1,
                'delivery_status' => $request->delivery_status?->value ?? 'unknown',
            ],
        );
    }

    /**
     * Registra la copia de token
     */
    public static function logTokenCopied(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::TokenCopied,
            $user,
            $ip,
            $userAgent,
        );
    }

    /**
     * Registra la confirmación de entrega
     */
    public static function logDeliveryConfirmed(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $deliveryMethod = null,
        ?string $deliveryReason = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::DeliveryConfirmed,
            $user,
            $ip,
            $userAgent,
            details: [
                'delivery_method' => $deliveryMethod,
                'delivery_reason' => $deliveryReason,
                'previous_status' => $request->delivery_status?->value ?? 'unknown',
                'new_status' => 'delivered',
            ],
        );
    }

    /**
     * Registra la cancelación de aprobación
     */
    public static function logApprovalCancelled(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $reason = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::ApprovalCancelled,
            $user,
            $ip,
            $userAgent,
            details: [
                'reason' => $reason,
                'previous_status' => $request->status?->value ?? 'unknown',
            ],
        );
    }

    /**
     * Registra la regeneración de token
     */
    public static function logTokenRegenerated(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $reason = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::TokenRegenerated,
            $user,
            $ip,
            $userAgent,
            details: [
                'reason' => $reason,
                'previous_status' => $request->status?->value ?? 'unknown',
            ],
        );
    }

    /**
     * Registra un acceso denegado a datos protegidos
     */
    public static function logProtectedDataViewDenied(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $reason = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::ProtectedDataViewDenied,
            $user,
            $ip,
            $userAgent,
            details: [
                'reason' => $reason,
            ],
        );
    }

    /**
     * Registra un acceso denegado a revelación de token
     */
    public static function logTokenRevealDenied(
        ApiTokenRequest $request,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $reason = null,
    ): void {
        self::log(
            $request,
            TokenRequestAuditAction::TokenRevealDenied,
            $user,
            $ip,
            $userAgent,
            details: [
                'reason' => $reason,
            ],
        );
    }

    /**
     * Obtiene todos los logs de auditoría de una solicitud
     */
    public static function getAuditTrail(ApiTokenRequest $request): array
    {
        return $request->auditLogs()
            ->with('user')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (TokenRequestAuditLog $log) => [
                'action' => $log->action->label(),
                'user' => $log->user?->name ?? 'Sistema',
                'ip' => $log->ip_address,
                'timestamp' => $log->created_at,
                'details' => $log->details,
            ])
            ->toArray();
    }

    /**
     * Obtiene los logs sensibles de una solicitud
     */
    public static function getSensitiveAuditTrail(ApiTokenRequest $request): array
    {
        return TokenRequestAuditLog::getSensitiveLogsForRequest($request)
            ->map(fn (TokenRequestAuditLog $log) => [
                'action' => $log->action->label(),
                'user' => $log->user?->name ?? 'Sistema',
                'ip' => $log->ip_address,
                'timestamp' => $log->created_at,
                'data_type' => $log->sensitive_data_type,
            ])
            ->toArray();
    }

    /**
     * Verifica si un usuario ha sido auditado para una acción específica
     */
    public static function hasUserPerformed(
        ApiTokenRequest $request,
        TokenRequestAuditAction $action,
        ?User $user = null,
    ): bool {
        $query = TokenRequestAuditLog::where('api_token_request_id', $request->id)
            ->where('action', $action);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->exists();
    }

    /**
     * Obtiene la información de entrega (quién, cuándo, cómo)
     */
    public static function getDeliveryInfo(ApiTokenRequest $request): ?array
    {
        $log = TokenRequestAuditLog::getDeliveryInfo($request);

        if (! $log) {
            return null;
        }

        return [
            'delivered_by' => $log->user?->name ?? 'Sistema',
            'delivered_at' => $log->created_at,
            'delivery_method' => $log->details['delivery_method'] ?? null,
            'delivery_reason' => $log->details['delivery_reason'] ?? null,
            'ip_address' => $log->ip_address,
        ];
    }
}
