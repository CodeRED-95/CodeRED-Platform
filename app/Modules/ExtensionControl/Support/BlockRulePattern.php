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

    public static function dayLabel(int $day): string
    {
        return self::DAYS[$day] ?? (string) $day;
    }
}
