<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/**
 * Usuario en el área de administración móvil.
 *
 * Sólo lo necesario para identificar a una persona y ver su acceso. Nada de
 * `password`, `remember_token`, identificadores de Telegram ni tokens: son
 * datos internos que la app no necesita para nada.
 */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof User) {
            throw new LogicException('AdminUserResource requiere un usuario.');
        }

        $user = $this->resource;

        return [
            'id' => $user->id,
            'nombre' => $user->name,
            'email' => $user->email,
            'estado' => $user->status,
            'activo' => $user->isActive(),
            'roles' => $user->relationLoaded('roles')
                ? $user->roles->map(fn ($role): array => [
                    'slug' => $role->slug,
                    'nombre' => $role->name,
                ])->values()->all()
                : [],
            'ultimo_acceso_en' => $user->last_login_at?->toIso8601String(),
            'creado_en' => $user->created_at?->toIso8601String(),
        ];
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
