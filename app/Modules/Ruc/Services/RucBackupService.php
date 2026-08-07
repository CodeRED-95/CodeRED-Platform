<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class RucBackupService
{
    private const BACKUP_DIR = 'backups/ruc'; // storage/app/backups/ruc

    private const COMPRESSION_LEVEL = 6; // 1-9, 6 es balance entre velocidad y compresión

    public function __construct()
    {
        if (!Storage::disk('local')->exists(self::BACKUP_DIR)) {
            Storage::disk('local')->makeDirectory(self::BACKUP_DIR);
        }
    }

    /**
     * Realizar backup completo de la tabla ruc_records
     */
    public function backup(string $backupType = 'full', ?User $user = null): RucBackup
    {
        $startTime = microtime(true);
        $timestamp = now()->format('Y-m-d-His');
        $backupName = "ruc_backup_{$timestamp}.sql.gz";
        $localPath = storage_path('app/' . self::BACKUP_DIR . '/' . $backupName);

        try {
            Log::info('Starting RUC database backup', ['backup_type' => $backupType, 'user_id' => $user?->id]);

            // 1. Crear dump de la tabla ruc_records PRIMERO
            $this->createDump($localPath);

            // 2. Validar que el archivo fue creado
            if (!file_exists($localPath)) {
                throw new \Exception('Dump file was not created at ' . $localPath);
            }

            // 3. Obtener información del backup
            $fileSize = filesize($localPath);
            if ($fileSize === 0) {
                @unlink($localPath);
                throw new \Exception('Dump file is empty');
            }

            $checksum = hash_file('sha256', $localPath);
            $recordCount = DB::table('ruc_records')->count();
            $duration = intval(microtime(true) - $startTime);

            // 4. Crear registro de backup CON todos los datos
            $backup = RucBackup::create([
                'name' => $backupName,
                'backup_type' => $backupType,
                'storage_type' => 'local',
                'storage_path' => $localPath,
                'status' => 'completed',
                'started_at' => now(),
                'completed_at' => now(),
                'total_records' => $recordCount,
                'file_size_bytes' => $fileSize,
                'checksum_sha256' => $checksum,
                'duration_seconds' => $duration,
                'created_by' => $user?->id,
            ]);

            Log::info('RUC backup completed successfully', [
                'backup_id' => $backup->id,
                'file_name' => $backupName,
                'file_size' => $this->formatBytes($fileSize),
                'records' => $recordCount,
                'duration' => $duration . 's',
            ]);

            return $backup;

        } catch (\Throwable $e) {
            Log::error('RUC backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Limpiar archivo si existe
            if (isset($localPath) && file_exists($localPath)) {
                @unlink($localPath);
            }

            throw $e;
        }
    }

    /**
     * Restaurar desde un backup
     */
    public function restore(RucBackup $backup, bool $dryRun = true): array
    {
        if ($backup->status !== 'completed') {
            throw new \Exception('El backup debe estar completado para restaurar');
        }

        if (!file_exists($backup->storage_path)) {
            throw new \Exception('El archivo de backup no existe: ' . $backup->storage_path);
        }

        Log::info('Starting RUC database restore', [
            'backup_id' => $backup->id,
            'dry_run' => $dryRun,
        ]);

        $startTime = microtime(true);

        try {
            // 1. Validar checksum
            if ($backup->checksum_sha256) {
                $calculatedChecksum = hash_file('sha256', $backup->storage_path);
                if ($calculatedChecksum !== $backup->checksum_sha256) {
                    throw new \Exception('Checksum validation failed - backup may be corrupted');
                }
            }

            // 2. Restaurar en BD
            if (!$dryRun) {
                // Backup actual antes de restaurar
                $safetyBackup = $this->backup('safety_before_restore');
                Log::warning('Safety backup created before restore', ['backup_id' => $safetyBackup->id]);

                $this->restoreFromDump($backup->storage_path);
            }

            $duration = intval(microtime(true) - $startTime);

            Log::info('RUC database restore completed', [
                'backup_id' => $backup->id,
                'duration' => $duration . 's',
                'dry_run' => $dryRun,
            ]);

            return [
                'success' => true,
                'backup_id' => $backup->id,
                'records_restored' => $backup->total_records,
                'duration_seconds' => $duration,
            ];

        } catch (\Throwable $e) {
            Log::error('RUC restore failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Crear dump SQL comprimido
     */
    private function createDump(string $outputPath): void
    {
        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbHost = config('database.connections.pgsql.host');
        $dbPort = config('database.connections.pgsql.port', 5432);

        // Asegurar que el directorio existe
        $backupDir = dirname($outputPath);
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // Usar pg_dump con compresión
        $command = [
            'pg_dump',
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--username=' . $dbUser,
            '--table=ruc_records',
            '--compress=' . self::COMPRESSION_LEVEL,
            '--format=custom',
            '--file=' . $outputPath,
            $dbName,
        ];

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hora de timeout
        $process->setEnv(['PGPASSWORD' => config('database.connections.pgsql.password')]);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            Log::error('pg_dump command failed', [
                'command' => implode(' ', $command),
                'error' => $e->getMessage(),
                'stderr' => $process->getErrorOutput(),
            ]);
            throw new \Exception('Failed to create backup dump: ' . $process->getErrorOutput());
        }

        if (!file_exists($outputPath)) {
            throw new \Exception('Dump file was not created at ' . $outputPath);
        }

        if (filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \Exception('Dump file is empty - no records to backup or pg_dump failed');
        }
    }

    /**
     * Restaurar desde dump
     */
    private function restoreFromDump(string $filePath): void
    {
        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbHost = config('database.connections.pgsql.host');
        $dbPort = config('database.connections.pgsql.port', 5432);

        // Limpiar tabla antes de restaurar
        DB::statement('TRUNCATE TABLE ruc_records CASCADE');

        $command = [
            'pg_restore',
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--username=' . $dbUser,
            '--dbname=' . $dbName,
            '--single-transaction',  // Una transacción para atomicidad
            '--jobs=4',              // Restaurar en paralelo
            $filePath,
        ];

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hora de timeout
        $process->setEnv(['PGPASSWORD' => config('database.connections.pgsql.password')]);

        $process->mustRun();
    }

    /**
     * Formatear bytes a formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Limpiar backups expirados
     */
    public function cleanupExpiredBackups(): void
    {
        $expired = RucBackup::expired()->get();

        foreach ($expired as $backup) {
            try {
                if (file_exists($backup->storage_path)) {
                    @unlink($backup->storage_path);
                }

                $backup->update(['status' => 'deleted']);
                Log::info('Backup deleted', ['backup_id' => $backup->id]);

            } catch (\Throwable $e) {
                Log::error('Failed to delete backup', [
                    'backup_id' => $backup->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
