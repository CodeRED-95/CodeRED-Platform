<?php

namespace RucTool\Services;

/**
 * Construye, lee y valida el manifest.json de un backup dividido en partes.
 * No conoce PostgreSQL ni la base de datos: solo describe el conjunto de
 * archivos y permite detectar parte corrupta/incompleta/faltante o un
 * archivo ensamblado incorrecto.
 */
class ManifestService
{
    public const FORMAT_VERSION = 1;

    private const SUPPORTED_FORMAT_VERSIONS = [1];

    public function build(
        string $originalFilename,
        int $totalRecords,
        int $totalSizeBytes,
        int $partSizeBytes,
        string $sha256,
        array $parts,
        string $toolVersion
    ): array {
        return [
            'format_version' => self::FORMAT_VERSION,
            'tool' => 'ruc-tools',
            'tool_version' => $toolVersion,
            'backup_type' => 'ruc_records',
            'created_at' => date('c'),
            'original_filename' => $originalFilename,
            'total_records' => $totalRecords,
            'total_size_bytes' => $totalSizeBytes,
            'part_size_bytes' => $partSizeBytes,
            'total_parts' => count($parts),
            'sha256' => $sha256,
            // Intencionalmente NO incluye host/usuario/password/connection
            // string/tokens: el manifest puede compartirse sin filtrar
            // credenciales ni infraestructura.
            'parts' => array_map(static fn (array $p): array => [
                'index' => $p['index'],
                'filename' => $p['filename'],
                'size_bytes' => $p['size_bytes'],
                'sha256' => $p['sha256'],
            ], $parts),
        ];
    }

    public function write(string $path, array $manifest): void
    {
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \Exception('No se pudo generar el manifest JSON.');
        }

        if (file_put_contents($path, $json . "\n") === false) {
            throw new \Exception("No se pudo escribir el manifest: {$path}");
        }
    }

    /**
     * El manifest es un JSON pequeño (KB, nunca proporcional al backup) —
     * leerlo completo en memoria no viola el requisito de streaming, que
     * aplica al backup en sí (potencialmente GB).
     */
    public function read(string $path): array
    {
        if (!file_exists($path)) {
            throw new \Exception("Manifest no encontrado: {$path}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \Exception("No se pudo leer el manifest: {$path}");
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new \Exception("Manifest inválido (JSON malformado): {$path}");
        }

        return $data;
    }

    /**
     * Devuelve la lista de errores encontrados (vacía = manifest válido).
     * Reconstruye el SHA-256 total por streaming (BackupPartitioner::
     * streamingSha256) sin escribir ningún archivo temporal.
     */
    public function validate(array $manifest, string $manifestDir, BackupPartitioner $partitioner): array
    {
        $errors = [];

        foreach (['format_version', 'total_parts', 'sha256', 'parts', 'total_size_bytes', 'part_size_bytes'] as $key) {
            if (!array_key_exists($key, $manifest)) {
                $errors[] = "Manifest incompleto: falta la clave \"{$key}\".";
            }
        }
        if (!empty($errors)) {
            return $errors;
        }

        if (!in_array($manifest['format_version'], self::SUPPORTED_FORMAT_VERSIONS, true)) {
            $errors[] = "format_version {$manifest['format_version']} no soportado por esta versión de ruc-tools.";

            return $errors;
        }

        $parts = $manifest['parts'];
        if (!is_array($parts) || count($parts) !== (int) $manifest['total_parts']) {
            $errors[] = 'El número de partes en el manifest no coincide con "total_parts".';

            return $errors;
        }

        // Orden correcto: index 1..N sin huecos ni duplicados.
        $indexes = array_map(static fn (array $p): int => (int) ($p['index'] ?? 0), $parts);
        $expectedIndexes = range(1, count($parts));
        if ($indexes !== $expectedIndexes) {
            $errors[] = 'Las partes no están en el orden correcto (se esperaba index 1..N consecutivo).';

            return $errors;
        }

        $totalSize = 0;
        $partPaths = [];

        foreach ($parts as $part) {
            $filename = $part['filename'] ?? null;
            $index = $part['index'] ?? '?';

            if (!$filename) {
                $errors[] = "Parte #{$index}: falta \"filename\" en el manifest.";
                continue;
            }

            $partPath = $manifestDir . '/' . $filename;
            $partPaths[] = $partPath;

            if (!file_exists($partPath)) {
                $errors[] = "Falta {$filename}.";
                continue;
            }

            $actualSize = filesize($partPath);
            if ($actualSize !== (int) $part['size_bytes']) {
                $errors[] = "Tamaño incorrecto en {$filename}: esperado {$part['size_bytes']} bytes, encontrado {$actualSize} bytes.";
                continue;
            }

            $actualSha = hash_file('sha256', $partPath);
            if ($actualSha !== $part['sha256']) {
                $errors[] = "Checksum incorrecto en {$filename}.";
                continue;
            }

            $totalSize += $actualSize;
        }

        if (!empty($errors)) {
            return $errors;
        }

        if ($totalSize !== (int) $manifest['total_size_bytes']) {
            $errors[] = "Tamaño total incorrecto: esperado {$manifest['total_size_bytes']} bytes, suma de partes {$totalSize} bytes.";

            return $errors;
        }

        $reconstructedSha = $partitioner->streamingSha256($partPaths);
        if ($reconstructedSha !== $manifest['sha256']) {
            $errors[] = 'El SHA-256 reconstruido a partir de las partes no coincide con el del manifest (archivo ensamblado incorrecto).';
        }

        return $errors;
    }
}
