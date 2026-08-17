<?php

declare(strict_types=1);

namespace App\Services\Permissions;

/**
 * Accesos móviles que un usuario puede pedir y un administrador conceder.
 *
 * Es la **lista blanca** del sistema: el endpoint de solicitudes sólo acepta
 * estas claves, así que manipular la petición para pedir `users.delete` o
 * cualquier otro permiso no lleva a ninguna parte. Ampliarla es una decisión
 * explícita que se toma aquí, no algo que pueda ocurrir por descuido.
 *
 * Cada acceso son dos cosas: el permiso RBAC que de verdad decide —el mismo que
 * comprueba el resto de la plataforma— y el rol que lo transporta.
 *
 * Lo segundo merece explicación. `User::hasPermission()` resuelve únicamente a
 * través de roles: no existe una tabla que ligue permisos a usuarios. Para dar
 * acceso a RUC sin convertir a nadie en administrador hay dos caminos: añadir
 * esa tabla y tocar la función de autorización más crítica de la aplicación, o
 * usar un rol que contenga exactamente ese permiso. Se eligió lo segundo. Un
 * usuario queda como «Consulta + Acceso RUC», retirarlo es quitar el rol, y la
 * lógica sigue preguntando por el permiso —nunca por el nombre del rol—.
 */
final class MobileAccess
{
    public const RUC = 'ruc.view';

    public const DNI = 'dni-records.view';

    public const PLATFORM_APP = 'platform.access';

    public const MOBILE_APP = 'mobile.access';

    public const DESKTOP_APP = 'desktop.access';

    /** Acceso a un módulo funcional: el interesado puede solicitarlo desde la app. */
    public const SCOPE_MODULE = 'module';

    /**
     * Acceso a una aplicación cliente completa. Sólo lo concede administración:
     * no tiene sentido que alguien pida desde Mobile permiso para entrar en
     * Mobile, y decidir en qué clientes existe una cuenta es una decisión de
     * gobierno, no una petición del interesado.
     */
    public const SCOPE_APPLICATION = 'application';

    /**
     * @var array<string, array{role: string, role_name: string, label: string, description: string, scope: string}>
     */
    private const CATALOG = [
        self::RUC => [
            'role' => 'acceso-ruc',
            'role_name' => 'Acceso RUC',
            'label' => 'Consulta RUC',
            'description' => 'Consultar contribuyentes del padrón RUC desde la app.',
            'scope' => self::SCOPE_MODULE,
        ],
        self::DNI => [
            'role' => 'acceso-dni',
            'role_name' => 'Acceso DNI',
            'label' => 'Consulta DNI',
            'description' => 'Consultar identidad por documento desde la app.',
            'scope' => self::SCOPE_MODULE,
        ],
        self::PLATFORM_APP => [
            'role' => 'acceso-platform',
            'role_name' => 'Acceso CodeRED Platform',
            'label' => 'CodeRED Platform',
            'description' => 'Entrar al panel web de CodeRED Platform.',
            'scope' => self::SCOPE_APPLICATION,
        ],
        self::MOBILE_APP => [
            'role' => 'acceso-mobile',
            'role_name' => 'Acceso CodeRED Mobile',
            'label' => 'CodeRED Mobile',
            'description' => 'Iniciar sesión en la aplicación móvil.',
            'scope' => self::SCOPE_APPLICATION,
        ],
        self::DESKTOP_APP => [
            'role' => 'acceso-desktop',
            'role_name' => 'Acceso CodeRED Desktop',
            'label' => 'CodeRED Desktop',
            'description' => 'Iniciar sesión en el cliente de escritorio.',
            'scope' => self::SCOPE_APPLICATION,
        ],
    ];

    /**
     * Permisos que se pueden solicitar desde la app.
     *
     * @return list<string>
     */
    public static function requestable(): array
    {
        return array_keys(array_filter(
            self::CATALOG,
            static fn (array $meta): bool => $meta['scope'] === self::SCOPE_MODULE,
        ));
    }

    /**
     * Permisos que administración puede conceder o retirar por usuario.
     *
     * Es un superconjunto de requestable(): incluye además el acceso a cada
     * aplicación cliente, que nadie puede pedir para sí mismo.
     *
     * @return list<string>
     */
    public static function grantable(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function isGrantable(string $permission): bool
    {
        return array_key_exists($permission, self::CATALOG);
    }

    public static function scope(string $permission): string
    {
        return self::CATALOG[$permission]['scope'] ?? self::SCOPE_MODULE;
    }

    public static function isRequestable(string $permission): bool
    {
        return array_key_exists($permission, self::CATALOG)
            && self::CATALOG[$permission]['scope'] === self::SCOPE_MODULE;
    }

    /** Nombre con el que se presenta al usuario: "Consulta RUC". */
    public static function label(string $permission): string
    {
        return self::CATALOG[$permission]['label'] ?? $permission;
    }

    public static function description(string $permission): string
    {
        return self::CATALOG[$permission]['description'] ?? '';
    }

    /** Rol que transporta el permiso. */
    public static function role(string $permission): ?string
    {
        return self::CATALOG[$permission]['role'] ?? null;
    }

    public static function roleName(string $permission): string
    {
        return self::CATALOG[$permission]['role_name'] ?? $permission;
    }

    /**
     * Catálogo completo, para que la app pinte los accesos sin conocerlos.
     *
     * @return list<array{permission: string, label: string, description: string}>
     */
    public static function all(?string $scope = null): array
    {
        $accesos = [];

        foreach (self::CATALOG as $permission => $meta) {
            if ($scope !== null && $meta['scope'] !== $scope) {
                continue;
            }

            $accesos[] = [
                'permission' => $permission,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'scope' => $meta['scope'],
            ];
        }

        return $accesos;
    }
}
