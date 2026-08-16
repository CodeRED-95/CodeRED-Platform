<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Token de API en el listado administrativo.
 *
 * NUNCA serializa el valor del token. La columna `token` guarda un hash SHA-256
 * del que no se puede volver al original, así que ni siquiera existe algo que
 * mostrar: el valor plano sólo se ve una vez, en la respuesta de creación.
 */
class AdminTokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof ApiToken) {
            throw new LogicException('AdminTokenResource requiere un token.');
        }

        $token = $this->resource;

        return [
            'id' => $token->id,
            'nombre' => $token->name,
            'descripcion' => $token->description,
            'abilities' => array_values($token->abilities ?? []),
            'propietario' => $token->tokenable?->name,
            'propietario_tipo' => $this->ownerLabel($token),
            'estado' => $this->status($token),
            'creado_en' => $token->created_at?->toIso8601String(),
            'ultimo_uso_en' => $token->last_used_at?->toIso8601String(),
            'expira_en' => $token->expires_at?->toIso8601String(),
            'revocado_en' => $token->revoked_at?->toIso8601String(),
        ];
    }

    private function ownerLabel(ApiToken $token): string
    {
        return match ($token->tokenable_type) {
            User::class => 'usuario',
            ApiClient::class => 'cliente',
            default => 'desconocido',
        };
    }

    /** Estado derivado: la app no recalcula caducidades por su cuenta. */
    private function status(ApiToken $token): string
    {
        if ($token->revoked_at !== null) {
            return 'revocado';
        }

        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return 'vencido';
        }

        return 'activo';
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
