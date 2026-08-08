<?php

namespace RucTool\Services;

use RucTool\Database\Connection;

/**
 * Backup/restore de ruc_records usando pg_dump/pg_restore --format=custom,
 * idéntico a App\Modules\Ruc\Services\RucBackupService en CodeRED-Platform.
 * Los archivos generados aquí son restaurables directamente en producción
 * con `php artisan ruc:restore`, y viceversa.
 */
class BackupService
{
    private const COMPRESSION_LEVEL = 6;

    private Connection $connection;
    private array $dbConfig;
    private string $backupDir;

    public function __construct(Connection $connection, array $dbConfig, string $backupDir)
    {
        $this->connection = $connection;
        $this->dbConfig = $dbConfig;
        $this->backupDir = $backupDir;
        $this->ensureBackupDir();
    }

    private function ensureBackupDir(): void
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    public function backup(): array
    {
        $timestamp = date('Y-m-d-His');
        $filename = "ruc_backup_{$timestamp}.sql.gz";
        $backupPath = "{$this->backupDir}/{$filename}";

        $recordCount = $this->connection->count('ruc_records');

        $this->runPgDump($backupPath);

        if (!file_exists($backupPath)) {
            throw new \Exception('El archivo de backup no fue creado');
        }

        $fileSize = filesize($backupPath);
        $checksum = hash_file('sha256', $backupPath);

        $this->connection->insert('ruc_tool_backups', [
            'name' => $filename,
            'total_records' => $recordCount,
            'file_size_bytes' => $fileSize,
            'storage_path' => $backupPath,
            'checksum_sha256' => $checksum,
            'status' => 'completed',
        ]);

        return [
            'filename' => $filename,
            'path' => $backupPath,
            'size' => $fileSize,
            'records' => $recordCount,
            'checksum' => $checksum,
            'timestamp' => $timestamp,
        ];
    }

    private function runPgDump(string $outputPath): void
    {
        $command = sprintf(
            'PGPASSWORD=%s pg_dump --host=%s --port=%s --username=%s --table=ruc_records --compress=%d --format=custom --file=%s %s 2>&1',
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

    public function restore(string $backupFilename): array
    {
        $backupPath = "{$this->backupDir}/{$backupFilename}";

        if (!file_exists($backupPath)) {
            throw new \Exception("Backup no encontrado: $backupFilename");
        }

        $recordsBefore = $this->connection->count('ruc_records');

        // --clean --if-exists dentro de --single-transaction: pg_restore hace
        // DROP+CREATE+COPY de forma atómica. Si algo falla, no se pierde el
        // estado previo (a diferencia de un TRUNCATE manual antes de restaurar).
        $this->runPgRestore($backupPath);

        $recordsAfter = $this->connection->count('ruc_records');

        return [
            'success' => true,
            'records_before' => $recordsBefore,
            'records_after' => $recordsAfter,
            'backup_file' => $backupFilename,
        ];
    }

    private function runPgRestore(string $inputPath): void
    {
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

        return array_map(fn($b) => [
            'filename' => $b['name'],
            'size' => $this->formatBytes((int) $b['file_size_bytes']),
            'records' => $b['total_records'],
            'created' => $b['created_at'],
            'path' => $b['storage_path'],
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
}
