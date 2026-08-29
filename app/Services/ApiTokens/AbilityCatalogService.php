<?php

declare(strict_types=1);

namespace App\Services\ApiTokens;

use App\Models\Permission;

class AbilityCatalogService
{
    /**
     * @return array<int, array{ability: string, label: string, description: string, permission: string|null}>
     */
    public function options(): array
    {
        $labels = (array) config('api.abilities', []);
        $permissions = Permission::query()
            ->whereIn('slug', array_keys($labels))
            ->get(['slug', 'name'])
            ->keyBy('slug');

        $fallbackLabels = [
            'agencias:consultar' => 'Consultar agencias',
            'dni:consultar' => 'Consultar DNI',
            'dni:nombre' => 'Consultar DNI por nombres',
            'ruc:consultar' => 'Consultar RUC por número',
            'ruc:buscar' => 'Buscar RUC por razón social',
            'agencies:read' => 'Consultar agencias',
            'agencies:map' => 'Consultar mapa de agencias',
            'profile:read' => 'Consultar propietario del token',
            'mobile' => 'Acceso móvil CodeRED',
            'admin:tokens' => 'Administración de tokens',
            'admin:solicitudes' => 'Administración de solicitudes de token',
            'admin:usuarios' => 'Administración de usuarios',
            'shalom-recordar:sync' => 'Sincronizar Shalom Recordar',
            'shalom-recordar:read-own' => 'Consultar sincronizaciones propias',
        ];

        return collect($labels)
            ->map(function (string $label, string $ability) use ($permissions, $fallbackLabels): array {
                $permission = $permissions->get($ability);

                return [
                    'ability' => $ability,
                    'label' => $permission !== null ? $permission->name : ($fallbackLabels[$ability] ?? $label),
                    'description' => $label,
                    'permission' => $permission !== null ? $ability : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function allowedAbilities(): array
    {
        return array_values(array_map(fn (array $option): string => $option['ability'], $this->options()));
    }

    /**
     * @return list<string>
     */
    public function authorizedAbilitiesFor(?object $user): array
    {
        if (! $user) {
            return [];
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return $this->allowedAbilities();
        }

        $catalog = $this->options();

        // Cada ability se otorga si el usuario tiene AL MENOS UNO de los permisos listados.
        $map = [
            'agencias:consultar' => ['agencies.view'],
            'agencies:read' => ['agencies.view'],
            'agencies:map' => ['agencies.map'],
            'dni:consultar' => ['dni-records.view'],
            'dni:nombre' => ['dni-records.view'],
            'ruc:consultar' => ['ruc.view'],
            'ruc:buscar' => ['ruc.view'],
            'profile:read' => ['api-tokens.view-own', 'api-tokens.create-for-users'],
            'mobile' => ['mobile'],
            'admin:tokens' => ['api-tokens.view-any'],
            'admin:solicitudes' => ['api-token-requests.view'],
            'admin:usuarios' => ['users.view'],
            'shalom-recordar:sync' => ['shalom-recordar.sync'],
            'shalom-recordar:read-own' => ['shalom-recordar.view-own'],
        ];

        return collect($catalog)
            ->filter(function (array $option) use ($user, $map): bool {
                $permissions = $map[$option['ability']] ?? [];
                if ($permissions === []) {
                    return false;
                }

                return collect($permissions)->contains(fn (string $permission): bool => method_exists($user, 'hasPermission') && $user->hasPermission($permission));
            })
            ->map(fn (array $option): string => $option['ability'])
            ->values()
            ->all();
    }
}
