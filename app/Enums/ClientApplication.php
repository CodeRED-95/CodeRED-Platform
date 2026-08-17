<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Clientes oficiales de CodeRED que abren sesión de usuario.
 *
 * No incluye integraciones (n8n, agentes, bridges): esas no representan a una
 * persona y siguen autenticándose con un token de API.
 */
enum ClientApplication: string
{
    case Platform = 'platform';
    case Mobile = 'mobile';
    case Desktop = 'desktop';

    /** Permiso RBAC que habilita la entrada a esta aplicación. */
    public function accessPermission(): string
    {
        return match ($this) {
            self::Platform => 'platform.access',
            self::Mobile => 'mobile.access',
            self::Desktop => 'desktop.access',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'CodeRED Platform',
            self::Mobile => 'CodeRED Mobile',
            self::Desktop => 'CodeRED Desktop',
        };
    }

    /** Mensaje mostrado cuando la cuenta existe pero no alcanza esta aplicación. */
    public function accessDeniedMessage(): string
    {
        return 'Tu cuenta no tiene acceso a '.$this->label().'.';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
