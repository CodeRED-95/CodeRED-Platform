<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Support;

use RuntimeException;
use ZipArchive;

/**
 * Formato CodeRED RUC Backup (.rucbackup).
 *
 * UN SOLO archivo portable que por dentro contiene el padrón troceado:
 *
 *   ruc_backup_2026-08-09_120000.rucbackup      <- contenedor ZIP64
 *   ├── manifest.json                            <- metadatos + índice de chunks
 *   └── chunks/
 *       ├── 000001.csv.zst                       <- 500k filas, CSV comprimido con zstd
 *       ├── 000002.csv.zst
 *       └── ...
 *
 * ¿POR QUÉ ZIP64 Y NO tar.gz / un gzip único?
 *
 * Porque el directorio central del ZIP permite abrir el chunk N **sin leer
 * los N-1 anteriores**. Eso es exactamente lo que hace barata la reanudación:
 * si un restore murió en el chunk 23 de 37, se abre el 24 directamente. Con
 * un `.tar.zst` o un gzip único habría que descomprimir en secuencia todo lo
 * anterior solo para llegar al punto de corte, y el coste crecería con el
 * avance de la operación. ZIP64 además supera el límite de 4 GB del ZIP
 * clásico (libzip lo activa solo cuando hace falta) y `unzip -l` lo lista en
 * cualquier máquina, sin herramientas propias.
 *
 * Cada chunk se guarda con método STORE (sin comprimir por el ZIP): ya viene
 * comprimido con zstd. Comprimir dos veces solo gastaría CPU.
 *
 * NADA en esta clase carga un chunk entero en memoria: se escribe desde
 * ficheros temporales y se lee mediante ZipArchive::getStream(), que devuelve
 * un recurso PHP que se puede conectar directamente a la entrada de psql.
 */
final class RucBackupArchive
{
    public const FORMAT = 'codered-ruc-backup';

    /** Se incrementa solo ante cambios incompatibles del contenedor. */
    public const FORMAT_VERSION = 1;

    /** Versiones que este código sabe leer. */
    public const SUPPORTED_FORMAT_VERSIONS = [1];

    public const EXTENSION = 'rucbackup';

    public const MANIFEST_ENTRY = 'manifest.json';

    public const CHUNK_PREFIX = 'chunks/';

    public const COMPRESSION = 'zstd';

    /**
     * Columnas volcadas, en este orden exacto. El manifest las repite para
     * que un restore contra un esquema distinto falle de forma explícita en
     * vez de desplazar valores de columna silenciosamente.
     *
     * @var list<string>
     */
    public const COLUMNS = [
        'id', 'ruc', 'razon_social', 'estado', 'condicion', 'ubigeo',
        'tipo_via', 'nombre_via', 'codigo_zona', 'tipo_zona', 'numero',
        'interior', 'lote', 'departamento_direccion', 'manzana', 'kilometro',
        'departamento', 'provincia', 'distrito', 'direccion',
        'created_at', 'updated_at',
    ];

    public static function chunkEntryName(int $number): string
    {
        return self::CHUNK_PREFIX.str_pad((string) $number, 6, '0', STR_PAD_LEFT).'.csv.zst';
    }

    public static function isRucBackupFile(string $path): bool
    {
        if (! is_file($path) || filesize($path) === 0) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $found = $zip->locateName(self::MANIFEST_ENTRY) !== false;
        $zip->close();

        return $found;
    }

    /**
     * Abre el contenedor en modo lectura. El llamador debe cerrar con close().
     */
    public static function openForRead(string $path): ZipArchive
    {
        if (! is_file($path)) {
            throw new RuntimeException("El archivo de backup no existe: {$path}");
        }

        $zip = new ZipArchive;
        $code = $zip->open($path, ZipArchive::RDONLY);

        if ($code !== true) {
            throw new RuntimeException("No se pudo abrir el archivo .rucbackup (código {$code}). Puede estar corrupto o truncado.");
        }

        return $zip;
    }

    /**
     * Lee y decodifica el manifest. Es pequeño (unos KB incluso con cientos
     * de chunks), así que sí puede cargarse entero.
     *
     * @return array<string, mixed>
     */
    public static function readManifest(string $path): array
    {
        $zip = self::openForRead($path);

        try {
            $raw = $zip->getFromName(self::MANIFEST_ENTRY);

            if ($raw === false) {
                throw new RuntimeException('El archivo no contiene manifest.json: no es un .rucbackup válido.');
            }

            $manifest = json_decode($raw, true);

            if (! is_array($manifest)) {
                throw new RuntimeException('manifest.json no es un JSON válido.');
            }

            return $manifest;
        } finally {
            $zip->close();
        }
    }

    /**
     * Valida la coherencia del manifest sin tocar la base de datos.
     * Se ejecuta ANTES de crear ninguna tabla de staging.
     *
     * @param  array<string, mixed>  $manifest
     */
    public static function assertManifestIsValid(array $manifest): void
    {
        if (($manifest['format'] ?? null) !== self::FORMAT) {
            throw new RuntimeException('El archivo no declara el formato "'.self::FORMAT.'". No es un backup RUC.');
        }

        $version = (int) ($manifest['format_version'] ?? 0);
        if (! in_array($version, self::SUPPORTED_FORMAT_VERSIONS, true)) {
            throw new RuntimeException(sprintf(
                'Versión de formato %d no soportada (esta versión lee: %s). Actualiza CodeRED Platform.',
                $version,
                implode(', ', self::SUPPORTED_FORMAT_VERSIONS)
            ));
        }

        $columns = $manifest['columns'] ?? null;
        if (! is_array($columns) || $columns !== self::COLUMNS) {
            throw new RuntimeException(
                'Las columnas del backup no coinciden con el esquema actual de ruc_records. '.
                'Restaurarlo desplazaría valores entre columnas.'
            );
        }

        $chunks = $manifest['chunks'] ?? null;
        if (! is_array($chunks)) {
            throw new RuntimeException('El manifest no contiene la lista de chunks.');
        }

        $declaredTotal = (int) ($manifest['total_batches'] ?? -1);
        if ($declaredTotal !== count($chunks)) {
            throw new RuntimeException(sprintf(
                'El manifest declara %d lotes pero lista %d. El backup está incompleto.',
                $declaredTotal,
                count($chunks)
            ));
        }

        // Numeración: 1..N sin huecos ni repetidos. Un hueco significaría que
        // el restore saltaría filas sin darse cuenta.
        $numbers = array_map(static fn (array $c): int => (int) ($c['number'] ?? 0), $chunks);
        sort($numbers);
        if ($numbers !== range(1, count($chunks))) {
            throw new RuntimeException('La numeración de chunks tiene huecos o duplicados. El backup está incompleto.');
        }

        $sumRecords = array_sum(array_map(static fn (array $c): int => (int) ($c['records'] ?? 0), $chunks));
        $declaredRecords = (int) ($manifest['total_records'] ?? -1);
        if ($sumRecords !== $declaredRecords) {
            throw new RuntimeException(sprintf(
                'El manifest declara %d registros pero la suma de los chunks es %d.',
                $declaredRecords,
                $sumRecords
            ));
        }
    }

    /**
     * Comprueba que cada chunk declarado exista dentro del contenedor y que
     * su SHA-256 coincida. Es la validación cara (lee todo el archivo), así
     * que se ejecuta como paso explícito antes de tocar la base activa.
     *
     * @param  array<string, mixed>  $manifest
     * @param  null|callable(int, int): void  $onProgress  (chunkActual, totalChunks)
     */
    public static function verifyChunks(string $path, array $manifest, ?callable $onProgress = null): void
    {
        $zip = self::openForRead($path);

        try {
            /** @var list<array<string, mixed>> $chunks */
            $chunks = $manifest['chunks'];
            $total = count($chunks);

            foreach ($chunks as $index => $chunk) {
                $name = (string) $chunk['filename'];
                $stat = $zip->statName($name);

                if ($stat === false) {
                    throw new RuntimeException("Falta el chunk \"{$name}\" dentro del backup. Restauración rechazada.");
                }

                if ((int) $stat['size'] !== (int) $chunk['compressed_size']) {
                    throw new RuntimeException(sprintf(
                        'El chunk "%s" mide %d bytes pero el manifest declara %d. El backup está corrupto.',
                        $name,
                        $stat['size'],
                        $chunk['compressed_size']
                    ));
                }

                $stream = $zip->getStream($name);
                if ($stream === false) {
                    throw new RuntimeException("No se pudo leer el chunk \"{$name}\".");
                }

                // hash_update_stream mantiene memoria constante: nunca carga
                // el chunk completo aunque pese cientos de MB.
                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $stream);
                fclose($stream);
                $actual = hash_final($ctx);

                if (! hash_equals((string) $chunk['sha256'], $actual)) {
                    throw new RuntimeException(
                        "El checksum del chunk \"{$name}\" no coincide. El backup está corrupto; ".
                        'la restauración se cancela sin haber tocado ruc_records.'
                    );
                }

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * Devuelve un stream de lectura del chunk indicado. El llamador debe
     * hacer fclose() y mantener vivo el ZipArchive mientras lo usa.
     *
     * @return resource
     */
    public static function chunkStream(ZipArchive $zip, string $entryName)
    {
        $stream = $zip->getStream($entryName);

        if ($stream === false) {
            throw new RuntimeException("No se pudo abrir el chunk \"{$entryName}\" del backup.");
        }

        return $stream;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>|null
     */
    public static function findChunk(array $manifest, int $number): ?array
    {
        foreach ($manifest['chunks'] as $chunk) {
            if ((int) $chunk['number'] === $number) {
                return $chunk;
            }
        }

        return null;
    }
}
