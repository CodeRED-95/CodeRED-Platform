<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\ApiTokenRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Solicitud de token para el área de administración móvil.
 *
 * Expone lo justo para decidir sobre ella. Los datos de contacto de entrega van
 * SIEMPRE enmascarados: verlos completos exige el permiso
 * `api-token-requests.view-delivery-contact` y ocurre en el panel web, que
 * registra esa revelación como un evento aparte. La app no lo pide.
 *
 * Tampoco se expone `token_ciphertext`, `token_hash` ni `token_last_four`.
 */
class AdminTokenRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ApiTokenRequest) {
            throw new LogicException('AdminTokenRequestResource requiere una solicitud.');
        }

        $solicitud = $this->resource;
        $contacto = $solicitud->maskedDeliveryContact();

        return [
            'id' => $solicitud->id,
            'tracking_code' => $solicitud->tracking_code,
            'estado' => $solicitud->statusValue(),
            'estado_label' => $solicitud->status?->label(),
            'tipo_solicitud' => $solicitud->requestTypeValue(),
            'aplicacion' => $solicitud->application_name,
            'solicitante' => $solicitud->requester_name,
            'proposito' => $solicitud->purpose,
            'token_solicitado' => $solicitud->requested_token_name,
            'tipo_token_solicitado' => $solicitud->requested_token_type,
            'abilities_solicitadas' => array_values($solicitud->requested_abilities ?? []),
            'vigencia_solicitada_dias' => $solicitud->requested_token_expires_in_days,
            'canal_entrega' => $solicitud->delivery_method,
            'estado_entrega' => $solicitud->deliveryStatusValue(),
            // Enmascarado siempre: la app no revela contactos completos.
            'contacto_entrega' => $contacto,
            'motivo_rechazo' => $solicitud->rejection_reason,
            'solicitada_en' => $solicitud->requestedAt()?->toIso8601String(),
            'revisada_en' => $solicitud->reviewedAt()?->toIso8601String(),
            'revisada_por' => $solicitud->reviewer?->name,
        ];
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
