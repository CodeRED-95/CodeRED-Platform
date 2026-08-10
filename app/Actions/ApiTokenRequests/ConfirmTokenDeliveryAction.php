<?php

namespace App\Actions\ApiTokenRequests;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\AuditService;
use Illuminate\Support\Facades\DB;

class ConfirmTokenDeliveryAction
{
    /**
     * Confirma la entrega de un token
     *
     * Marca como "Entregado" y registra en auditoría
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     delivered_at: string,
     * }
     */
    public function execute(
        ApiTokenRequest $request,
        ?string $deliveryMethod = null,
        ?string $deliveryReason = null,
        $user = null,
        string $ip = null,
        ?string $userAgent = null,
    ): array {
        // Usar transacción para atomicidad
        $result = DB::transaction(function () use (
            $request,
            $deliveryMethod,
            $deliveryReason,
            $user,
            $ip,
            $userAgent
        ) {
            // Obtener request fresco
            $fresh = ApiTokenRequest::query()
                ->where('id', $request->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Verificar que el token fue revelado
            if (!$fresh->hasTokenBeenRevealed()) {
                throw new \InvalidArgumentException(
                    'El token debe haber sido revelado antes de confirmar la entrega.'
                );
            }

            // Marcar como entregado
            $fresh->update([
                'delivery_status' => ApiTokenRequestDeliveryStatus::Delivered->value,
                'delivered_at' => now(),
                'delivered_by' => $user?->id,
            ]);

            // Registrar en auditoría
            AuditService::logDeliveryConfirmed(
                $fresh,
                $user,
                $ip,
                $userAgent,
                $deliveryMethod,
                $deliveryReason,
            );

            return [
                'success' => true,
                'message' => 'Entrega confirmada exitosamente.',
                'delivered_at' => $fresh->delivered_at->toIso8601String(),
            ];
        }, attempts: 3);

        return $result;
    }
}
