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
