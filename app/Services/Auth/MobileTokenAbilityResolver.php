<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;

class MobileTokenAbilityResolver
{
    /**
     * Mapa explícito permiso RBAC => abilities Sanctum.
     *
     * @var array<string, list<string>>
     */
    private const PERMISSION_TO_ABILITIES = [
        'agencies.view' => ['agencias:consultar', 'agencies:read'],
        'dni-records.view' => ['dni:consultar'],
        'ruc.view' => ['ruc:consultar'],
        'declaracion-jurada.view' => ['declaraciones:gestionar'],
        // Áreas de administración. La ability abre el área; el permiso concreto
        // de cada acción (crear, revocar, aprobar, rechazar) lo comprueba el
        // controlador contra la base en cada petición, porque un token emitido
        // ayer podría conservar la ability después de que a la persona le
        // retiraran el permiso.
        'api-tokens.view-any' => ['admin:tokens'],
        'api-token-requests.view' => ['admin:solicitudes'],
        'users.view' => ['admin:usuarios'],
    ];

    /**
     * @return list<string>
     */
    public function resolve(User $user): array
    {
        $abilities = ['mobile'];

        foreach (self::PERMISSION_TO_ABILITIES as $permission => $mappedAbilities) {
            if (! $user->hasPermission($permission)) {
                continue;
            }

            $abilities = array_merge($abilities, $mappedAbilities);
        }

        return array_values(array_unique($abilities));
    }
}
