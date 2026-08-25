<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Puente único entre los dos vocabularios de autorización de CodeRED.
 *
 *   ability   -> lo que declara un token de API      (dni:consultar)
 *   permission-> lo que tiene una persona en el RBAC (dni-records.view)
 *
 * Las rutas se siguen declarando con la ability, que es el nombre público de la
 * capacidad y no cambia. Lo que cambia es cómo se comprueba:
 *
 *   token de integración -> la ability tiene que estar en el token
 *   sesión de usuario    -> el permiso tiene que estar en el RBAC, ahora mismo
 *
 * Esta clase es la única fuente de la correspondencia. MobileTokenAbilityResolver
 * la invierte para seguir emitiendo tokens legacy con las mismas abilities, de
 * modo que no existan dos listas que puedan divergir.
 */
final class AbilityPermissionMap
{
    /**
     * Ability => permiso RBAC exigido a una sesión de usuario.
     *
     * Un valor `null` significa que la ability no exige permiso adicional: basta
     * con tener sesión válida en la aplicación. Es el caso de leer el propio
     * perfil o de las capacidades que sólo delimitan superficie de la app.
     *
     * @var array<string, string|null>
     */
    private const ABILITY_TO_PERMISSION = [
        // Consulta funcional
        'dni:consultar' => 'dni-records.view',
        'ruc:consultar' => 'ruc.view',
        'ruc:buscar' => 'ruc.view',
        'agencias:consultar' => 'agencies.view',
        'agencies:read' => 'agencies.view',
        'agencies:map' => 'agencies.map',
        'declaraciones:gestionar' => 'declaracion-jurada.view',
        'shalom-recordar:sync' => 'shalom-recordar.sync',
        'shalom-recordar:read-own' => 'shalom-recordar.view-own',

        // Control horario de la extension. Se concede token a token desde el
        // panel; para una sesion de usuario basta con poder ver el panel.
        'extension:blocking' => 'settings.extension-blocking.view',

        // Áreas de administración expuestas a los clientes. La ability abre el
        // área; cada acción concreta vuelve a comprobar su permiso en el
        // controlador, porque abrir la pantalla y poder revocar no son lo mismo.
        'admin:tokens' => 'api-tokens.view-any',
        'admin:solicitudes' => 'api-token-requests.view',
        'admin:usuarios' => 'users.view',
        'admin:accesos' => 'permission-requests.view',

        // Superficie de aplicación, no capacidad funcional.
        'profile:read' => null,
        'mobile' => 'mobile.access',
    ];

    /**
     * Permiso exigido a una sesión de usuario para ejercer esta ability.
     *
     * Una ability desconocida devuelve la propia cadena: así, si mañana se añade
     * una ruta con una ability nueva y se olvida mapearla, el efecto es denegar
     * (no existirá tal permiso) en lugar de permitir. Fallar cerrado.
     */
    public static function permissionFor(string $ability): ?string
    {
        if (! array_key_exists($ability, self::ABILITY_TO_PERMISSION)) {
            return $ability;
        }

        return self::ABILITY_TO_PERMISSION[$ability];
    }

    public static function isKnownAbility(string $ability): bool
    {
        return array_key_exists($ability, self::ABILITY_TO_PERMISSION);
    }

    /**
     * Abilities que corresponden a un permiso RBAC concreto.
     *
     * @return list<string>
     */
    public static function abilitiesForPermission(string $permission): array
    {
        $abilities = [];

        foreach (self::ABILITY_TO_PERMISSION as $ability => $mapped) {
            if ($mapped === $permission) {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }

    /** @return array<string, string|null> */
    public static function all(): array
    {
        return self::ABILITY_TO_PERMISSION;
    }
}
