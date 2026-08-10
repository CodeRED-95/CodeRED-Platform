<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Fuente única de verdad de la versión de CodeRED Platform.
 *
 * La versión vive en un solo sitio, `composer.json > extra.version`, y todo lo
 * demás (configuración de Laravel, UI, API, comandos y scripts de despliegue)
 * la lee desde aquí. Antes estaba duplicada en `.env`, `.env.example`,
 * `config/version.php` y `config/app.php`, y cualquier copia desactualizada
 * ganaba silenciosamente: una instalación con `APP_VERSION=3.2.0` heredado en
 * su `.env` reportaba esa versión aunque el código fuese 3.4.0.
 *
 * Esta clase se resuelve sin contenedor ni helpers de Laravel, porque los
 * ficheros de configuración se evalúan antes de que la aplicación arranque.
 */
final class Version
{
    /**
     * Formato SemVer aceptado: MAJOR.MINOR.PATCH, con prerelease/build opcionales.
     */
    public const PATTERN = '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/';

    /**
     * Valor de respaldo si `composer.json` no se puede leer. Nunca debería
     * usarse en una instalación sana; existe para que la aplicación arranque
     * (y muestre algo evidente) en lugar de romperse al cargar la config.
     */
    public const UNKNOWN = '0.0.0';

    private static ?string $cached = null;

    /**
     * Versión actual del proyecto.
     */
    public static function current(): string
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        return self::$cached = self::readFromComposer() ?? self::UNKNOWN;
    }

    /**
     * Ruta absoluta de la fuente de verdad.
     */
    public static function sourcePath(): string
    {
        return dirname(__DIR__, 2).'/composer.json';
    }

    /**
     * Escribe una versión nueva en la fuente de verdad, preservando el resto
     * del fichero. Solo la usa `app:bump-version`.
     *
     * @throws RuntimeException si la versión no es SemVer o el fichero no se puede escribir
     */
    public static function write(string $version): void
    {
        if (! self::isValid($version)) {
            throw new RuntimeException("La versión '{$version}' no sigue el formato SemVer MAJOR.MINOR.PATCH.");
        }

        $path = self::sourcePath();
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException("No se pudo leer {$path}.");
        }

        /** @var array<string, mixed>|null $composer */
        $composer = json_decode($raw, true);

        if (! is_array($composer)) {
            throw new RuntimeException("{$path} no contiene JSON válido.");
        }

        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $extra['version'] = $version;
        $composer['extra'] = $extra;

        $encoded = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false || @file_put_contents($path, $encoded."\n") === false) {
            throw new RuntimeException("No se pudo escribir {$path}.");
        }

        self::$cached = $version;
    }

    public static function isValid(string $version): bool
    {
        return preg_match(self::PATTERN, $version) === 1;
    }

    /**
     * Descompone una versión en sus tres componentes numéricos.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function parts(string $version): array
    {
        if (! self::isValid($version)) {
            throw new RuntimeException("La versión '{$version}' no sigue el formato SemVer MAJOR.MINOR.PATCH.");
        }

        // Se descartan prerelease y build: el bump siempre parte del núcleo.
        $core = preg_split('/[-+]/', $version)[0];
        [$major, $minor, $patch] = array_map('intval', explode('.', (string) $core));

        return [$major, $minor, $patch];
    }

    /**
     * Calcula la siguiente versión según el tipo de cambio (SemVer).
     */
    public static function bump(string $version, string $type): string
    {
        [$major, $minor, $patch] = self::parts($version);

        return match ($type) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            'patch' => $major.'.'.$minor.'.'.($patch + 1),
            default => throw new RuntimeException("Tipo de bump inválido: '{$type}'. Use major, minor o patch."),
        };
    }

    /**
     * Vacía la caché estática. Solo necesario en tests.
     */
    public static function forget(): void
    {
        self::$cached = null;
    }

    private static function readFromComposer(): ?string
    {
        $raw = @file_get_contents(self::sourcePath());

        if ($raw === false) {
            return null;
        }

        /** @var array<string, mixed>|null $composer */
        $composer = json_decode($raw, true);
        $version = is_array($composer) && is_array($composer['extra'] ?? null)
            ? ($composer['extra']['version'] ?? null)
            : null;

        return is_string($version) && self::isValid($version) ? $version : null;
    }
}
