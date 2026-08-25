<?php

declare(strict_types=1);

namespace App\Modules\ExtensionControl\Support;

/**
 * El panel solo puede bloquear hosts del dominio corporativo: la extension
 * declara host_permissions unicamente para *.shalomcontrol.com, asi que un
 * patron fuera de ese dominio jamas llegaria a aplicarse en el navegador.
 */
final class BlockRulePattern
{
    public const ALLOWED_DOMAIN = 'shalomcontrol.com';

    public const DAYS = [
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miercoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sabado',
    ];

    public static function normalizeHost(string $host): string
    {
        $host = trim(mb_strtolower($host));
        $host = preg_replace('#^[a-z]+://#', '', $host) ?? $host;

        return rtrim(explode('/', $host)[0], '.');
    }

    public static function hostIsAllowed(string $host): bool
    {
        $host = self::normalizeHost($host);
        if ($host === '') {
            return false;
        }

        $bare = str_starts_with($host, '*.') ? substr($host, 2) : $host;

        return $bare === self::ALLOWED_DOMAIN || str_ends_with($bare, '.'.self::ALLOWED_DOMAIN);
    }

    public static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/*';
        }
        if (! str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        return mb_strtolower(rtrim($path, '/')) ?: '/';
    }

    /**
     * Parte una linea del panel en dominio y ruta. Acepta lo que un operador
     * copia de la barra del navegador:
     *
     *   https://sysprovincia2.shalomcontrol.com/ordenservicio/listar?x=1
     *   sysprovincia2.shalomcontrol.com/ordenservicio/listar
     *   *.shalomcontrol.com
     *
     * La ruta vuelve como null cuando la linea solo trae dominio: en ese caso
     * el destino hereda la ruta de la regla.
     *
     * @return array{host: string, path: string|null}
     */
    public static function parseDestination(string $line): array
    {
        $value = trim($line);
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value) ?? $value;
        $value = explode('#', $value)[0];
        $value = explode('?', $value)[0];

        $slash = strpos($value, '/');

        if ($slash === false) {
            return ['host' => self::normalizeHost($value), 'path' => null];
        }

        $host = self::normalizeHost(substr($value, 0, $slash));
        $path = substr($value, $slash);

        return [
            'host' => $host,
            'path' => trim($path, '/') === '' ? null : self::normalizePath($path),
        ];
    }

    public static function dayLabel(int $day): string
    {
        return self::DAYS[$day] ?? (string) $day;
    }
}
