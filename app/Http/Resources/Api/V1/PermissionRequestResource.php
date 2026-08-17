<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PermissionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PermissionRequest
 *
 * La solicitud tal como la ve su propio autor. No incluye quién la revisó: al
 * interesado le importa la decisión, no de qué administrador vino.
 */
class PermissionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'permission' => $this->permission,
            'acceso' => $this->accessLabel(),
            'estado' => $this->status->value,
            'estado_label' => $this->status->label(),
            'motivo' => $this->reason,
            'motivo_rechazo' => $this->rejection_reason,
            'solicitada_en' => $this->requested_at?->toIso8601String(),
            'revisada_en' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
