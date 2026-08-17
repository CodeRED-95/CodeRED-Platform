<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\PermissionRequest;
use App\Services\Permissions\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PermissionRequest
 *
 * La solicitud como la ve quien decide.
 *
 * Lleva lo justo para poder decidir con criterio —quién pide, con qué rol, qué
 * acceso y por qué— y nada más. El resto del perfil de la persona no ayuda a
 * resolver la solicitud y no tiene por qué aparecer aquí.
 */
class AdminPermissionRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),

            'usuario' => [
                'id' => $this->user?->getKey(),
                'nombre' => $this->user?->name,
                'email' => $this->user?->email,
                'roles' => $this->user?->roles->pluck('name')->values()->all() ?? [],
            ],

            'permission' => $this->permission,
            'acceso' => $this->accessLabel(),
            'acceso_descripcion' => MobileAccess::description($this->permission),

            'estado' => $this->status->value,
            'estado_label' => $this->status->label(),
            'estado_tono' => match ($this->status->value) {
                'pending' => 'Caution',
                'approved' => 'Positive',
                'rejected' => 'Negative',
                default => 'Neutral',
            },

            'motivo' => $this->reason,
            'motivo_rechazo' => $this->rejection_reason,

            'solicitada_en' => $this->requested_at?->toIso8601String(),
            'revisada_en' => $this->reviewed_at?->toIso8601String(),
            'revisada_por' => $this->reviewer?->name,
        ];
    }
}
