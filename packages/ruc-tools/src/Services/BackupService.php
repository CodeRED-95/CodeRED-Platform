<?php

namespace RucTool\Services;

use RucTool\Database\Connection;

/**
 * Backup/restore de ruc_records.
 *
 * Backup: UN SOLO pg_dump consistente (--format=custom, tabla completa con
 * su schema — ver DEVELOPMENT.md/README.md para por qué NO es --data-only)
 * seguido de validación de contenido, checksum, y división binaria en
 * partes de tamaño fijo (streaming, nunca varios pg_dump independientes).
 *
 * Restore: acepta un .dump/.sql.gz de un solo archivo (comportamiento
 * histórico, sin cambios) o un manifest.json de un backup dividido (join
 * en streaming a un temporal, pg_restore, borra el temporal).
 */
class BackupService
{
    private const COMPRESSION_LEVEL = 6;

    /** 90 MiB — ver README.md para el porqué de este tamaño (transporte). */
    public const DEFAULT_PART_SIZE_BYTES = 90 * 1024 * 1024; // 94371840

    private Connection $connection;
    private array $dbConfig;
    private string $backupDir;
    private DumpValidator $validator;
    private BackupPartitioner $partitioner;
    private ManifestService $manifestService;
    private string $toolVersion;

    public function __construct(Connection $connection, array $dbConfig, string $backupDir, string $toolVersion = '2.3.0')
    {
        $this->connection = $connection;
        $this->dbConfig = $dbConfig;
        $this->backupDir = $backupDir;
        $this->validator = new DumpValidator();
        $this->partitioner = new BackupPartitioner();
        $this->manifestService = new ManifestService();
        $this->toolVersion = $toolVersion;
        $this->ensureBackupDir();
    }

    private function ensureBackupDir(): void
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * @param callable|null $onStage fn(string $stage, array $context): void — hooks
     *        de progreso para que el Command imprima cada etapa en orden sin
     *        acoplar este servicio a SymfonyStyle. Etapas emitidas, en orden:
     *        'dump_created' {size_bytes}, 'validated' {}, 'checksummed'
     *        {checksum}, 'part_created' {part, total_parts}, 'verified' {}.
     */
    public function backup(int $partSizeBytes = self::DEFAULT_PART_SIZE_BYTES, bool $keepFull = false, ?callable $onStage = null): array
    {
        $emit = static function (string $stage, array $context = []) use ($onStage): void {
            if ($onStage !== null) {
                $onStage($stage, $context);
            }
        };

        $timestamp = date('Y-m-d-His');
        $baseName = "ruc_backup_{$timestamp}";
        $backupSetDir = "{$this->backupDir}/{$baseName}";
        $dumpFilename = "{$baseName}.dump";
        $fullDumpPath = "{$backupSetDir}/{$dumpFilename}";

        $recordCount = $this->connection->count('ruc_records');

        $this->checkDiskSpace($this->backupDir, 512 * 1024 * 1024, 'Espacio insuficiente para iniciar el backup (mínimo 512 MB libres).');

        if (!is_dir($backupSetDir) && !mkdir($backupSetDir, 0755, true) && !is_dir($backupSetDir)) {
            throw new \Exception("No se pudo crear el directorio del backup: {$backupSetDir}");
        }

        $this->runPgDump($fullDumpPath);

        if (!file_exists($fullDumpPath) || filesize($fullDumpPath) === 0) {
            throw new \Exception('pg_dump no generó un archivo válido (el dump está vacío o no se creó).');
        }

        $fileSize = filesize($fullDumpPath);
        $emit('dump_created', ['size_bytes' => $fileSize]);

        // Validar ANTES de dividir: si el dump no es válido, no queremos
        // generar un manifest "completed" a partir de basura.
        $this->validator->assertBelongsToRucRecords($fullDumpPath);
        $emit('validated');

        $checksum = hash_file('sha256', $fullDumpPath);
        $emit('checksummed', ['checksum' => $checksum]);

        // Durante el split coexisten el dump completo y sus partes: se
        // necesita ~1x más espacio libre además de lo que ya ocupa el dump.
        $this->checkDiskSpace(
            $backupSetDir,
            (int) ($fileSize * 1.1),
            'Espacio insuficiente para dividir el backup en partes.'
        );

        $parts = $this->partitioner->split($fullDumpPath, $backupSetDir, $dumpFilename, $partSizeBytes);

        foreach ($parts as $part) {
            $emit('part_created', ['part' => $part, 'total_parts' => count($parts)]);
        }

        // Verificación automática: reconstruir el SHA-256 total a partir de
        // las partes (streaming, sin escribir otro archivo completo) y
        // compararlo contra el del dump original.
        $partPaths = array_map(static fn (array $p): string => "{$backupSetDir}/{$p['filename']}", $parts);
        $reconstructedSha = $this->partitioner->streamingSha256($partPaths);
        if ($reconstructedSha !== $checksum) {
            throw new \Exception('La verificación tras dividir el backup falló: el checksum reconstruido a partir de las partes no coincide con el del dump original.');
        }
        $emit('verified');

        $manifest = $this->manifestService->build(
            $dumpFilename,
            $recordCount,
            $fileSize,
            $partSizeBytes,
            $checksum,
            $parts,
            $this->toolVersion
        );
        $manifestFilename = "{$baseName}.manifest.json";
        $manifestPath = "{$backupSetDir}/{$manifestFilename}";
        $this->manifestService->write($manifestPath, $manifest);

        if (!$keepFull) {
            @unlink($fullDumpPath);
        }

        $this->connection->insert('ruc_tool_backups', [
            'name' => $baseName,
            'total_records' => $recordCount,
            'file_size_bytes' => $fileSize,
            'storage_path' => $backupSetDir,
            'manifest_path' => $manifestPath,
            'total_parts' => count($parts),
            'part_size_bytes' => $partSizeBytes,
            'checksum_sha256' => $checksum,
            'status' => 'completed',
        ]);

        return [
            'name' => $baseName,
            'directory' => $backupSetDir,
            'manifest_path' => $manifestPath,
            'dump_filename' => $dumpFilename,
            'size' => $fileSize,
            'records' => $recordCount,
            'checksum' => $checksum,
            'timestamp' => $timestamp,
            'parts' => $parts,
            'part_size_bytes' => $partSizeBytes,
            'kept_full' => $keepFull,
        ];
    }

    private function checkDiskSpace(string $dir, int $requiredBytes, string $message): void
    {
        $free = @disk_free_space($dir);
        if ($free !== false && $free < $requiredBytes) {
            $freeMb = round($free / 1024 / 1024, 1);
            $requiredMb = round($requiredBytes / 1024 / 1024, 1);
            throw new \Exception("{$message} Disponibles: {$freeMb} MB, requeridos: ~{$requiredMb} MB.");
        }
    }

    private function runPgDump(string $outputPath): void
    {
        if (!$this->commandExists('pg_dump')) {
            throw new \Exception('pg_dump no está disponible en el PATH de este contenedor/máquina.');
        }

        $command = sprintf(
            'PGPASSWORD=%s pg_dump --host=%s --port=%s --username=%s --table=public.ruc_records --no-owner --no-privileges --compress=%d --format=custom --file=%s %s 2>&1',
            escapeshellarg($this->dbConfig['password'] ?? ''),
            escapeshellarg($this->dbConfig['host'] ?? 'localhost'),
            escapeshellarg((string) ($this->dbConfig['port'] ?? 5432)),
            escapeshellarg($this->dbConfig['username'] ?? ''),
            self::COMPRESSION_LEVEL,
            escapeshellarg($outputPath),
            escapeshellarg($this->dbConfig['database'] ?? 'ruc_db')
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('pg_dump falló: ' . implode("\n", $output));
        }
    }

    private function commandExists(string $binary): bool
    {
        exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null', $output, $code);

        return $code === 0 && !empty($output);
    }

    /**
     * Restore de un solo archivo (.dump nuevo o .sql.gz legado) — sin
     * cambios de comportamiento respecto a versiones anteriores.
     */
    public function restore(string $backupFilename): array
    {
        $backupPath = $this->resolveBackupPath($backupFilename);

        if (!file_exists($backupPath)) {
            throw new \Exception("Backup no encontrado: {$backupFilename}");
        }

        $recordsBefore = $this->connection->count('ruc_records');

        $this->runPgRestore($backupPath);

        $recordsAfter = $this->connection->count('ruc_records');

        return [
            'success' => true,
            'records_before' => $recordsBefore,
            'records_after' => $recordsAfter,
            'backup_file' => basename($backupFilename),
        ];
    }

    /**
     * Restore a partir de un manifest.json de un backup dividido: verifica
     * el manifest, reconstruye el .dump a un archivo temporal (streaming),
     * corre pg_restore, y borra el temporal — nunca carga las partes en RAM.
     */
    public function restoreFromManifest(string $manifestPath): array
    {
        if (!file_exists($manifestPath)) {
            throw new \Exception("Manifest no encontrado: {$manifestPath}");
        }

        $manifest = $this->manifestService->read($manifestPath);
        $manifestDir = dirname($manifestPath);

        $errors = $this->manifestService->validate($manifest, $manifestDir, $this->partitioner);
        if (!empty($errors)) {
            throw new \Exception("El manifest no es válido:\n - " . implode("\n - ", $errors));
        }

        $this->checkDiskSpace($manifestDir, (int) ($manifest['total_size_bytes'] * 1.1), 'Espacio insuficiente para reconstruir el backup antes de restaurar.');

        $partPaths = array_map(
            static fn (array $p): string => "{$manifestDir}/{$p['filename']}",
            $manifest['parts']
        );

        $tempDump = $manifestDir . '/.tmp_restore_' . bin2hex(random_bytes(6)) . '.dump';

        try {
            $this->partitioner->join($partPaths, $tempDump);

            $actualSha = hash_file('sha256', $tempDump);
            if ($actualSha !== $manifest['sha256']) {
                throw new \Exception('El archivo reconstruido a partir de las partes no coincide con el checksum del manifest.');
            }

            $this->validator->assertBelongsToRucRecords($tempDump);

            $recordsBefore = $this->connection->count('ruc_records');
            $this->runPgRestore($tempDump);
            $recordsAfter = $this->connection->count('ruc_records');

            return [
                'success' => true,
                'records_before' => $recordsBefore,
                'records_after' => $recordsAfter,
                'backup_file' => basename($manifestPath),
            ];
        } finally {
            @unlink($tempDump);
        }
    }

    /**
     * Resuelve un nombre de backup relativo al directorio configurado, o
     * una ruta ya absoluta (permite restaurar backups copiados manualmente
     * desde otra máquina).
     */
    private function resolveBackupPath(string $backupFilename): string
    {
        if (str_starts_with($backupFilename, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $backupFilename) === 1) {
            return $backupFilename;
        }

        return "{$this->backupDir}/{$backupFilename}";
    }

    private function runPgRestore(string $inputPath): void
    {
        if (!$this->commandExists('pg_restore')) {
            throw new \Exception('pg_restore no está disponible en el PATH de este contenedor/máquina.');
        }

        // --clean --if-exists dentro de --single-transaction: pg_restore hace
        // DROP+CREATE+COPY de forma atómica. Si algo falla, no se pierde el
        // estado previo (a diferencia de un TRUNCATE manual antes de restaurar).
        // Requiere que el dump traiga schema (no --data-only) — ver README.md.
        $command = sprintf(
            'PGPASSWORD=%s pg_restore --host=%s --port=%s --username=%s --dbname=%s --clean --if-exists --single-transaction %s 2>&1',
            escapeshellarg($this->dbConfig['password'] ?? ''),
            escapeshellarg($this->dbConfig['host'] ?? 'localhost'),
            escapeshellarg((string) ($this->dbConfig['port'] ?? 5432)),
            escapeshellarg($this->dbConfig['username'] ?? ''),
            escapeshellarg($this->dbConfig['database'] ?? 'ruc_db'),
            escapeshellarg($inputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('pg_restore falló: ' . implode("\n", $output));
        }
    }

    public function listBackups(): array
    {
        $backups = $this->connection->select('ruc_tool_backups', [], ['created_at' => 'DESC']);

        return array_map(fn ($b) => [
            'filename' => $b['name'],
            'size' => $this->formatBytes((int) $b['file_size_bytes']),
            'records' => $b['total_records'],
            'created' => $b['created_at'],
            'path' => $b['storage_path'],
            'total_parts' => $b['total_parts'] ?? null,
        ], $backups);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getPartitioner(): BackupPartitioner
    {
        return $this->partitioner;
    }

    public function getManifestService(): ManifestService
    {
        return $this->manifestService;
    }
}
