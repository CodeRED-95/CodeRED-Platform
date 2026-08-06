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
    private const BACKUP_DIR = '/tmp/ruc-backups';

    private const COMPRESSION_LEVEL = 6; // 1-9, 6 es balance entre velocidad y compresión

    public function __construct()
    {
        if (!is_dir(self::BACKUP_DIR)) {
            mkdir(self::BACKUP_DIR, 0755, true);
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
        $localPath = self::BACKUP_DIR . '/' . $backupName;

        // Crear registro de backup
        $backup = RucBackup::create([
            'name' => $backupName,
            'backup_type' => $backupType,
            'storage_type' => 'local',
            'status' => 'pending',
            'started_at' => now(),
            'created_by' => $user?->id,
        ]);

        try {
            Log::info('Starting RUC database backup', ['backup_id' => $backup->id]);

            // 1. Crear dump de la tabla ruc_records
            $this->createDump($localPath);

            // 2. Obtener información del backup
            $fileSize = filesize($localPath);
            $checksum = hash_file('sha256', $localPath);
            $recordCount = DB::table('ruc_records')->count();

            // 3. Subir a S3 (si está configurado)
            $s3Path = null;
            if ($this->isS3Enabled()) {
                $s3Path = $this->uploadToS3($localPath, $backupName);
                // Eliminar archivo local después de subir
                @unlink($localPath);
                $storage = 's3';
            } else {
                $storage = 'local';
                $s3Path = $localPath;
            }

            // 4. Actualizar registro como completado
            $duration = intval(microtime(true) - $startTime);
            $backup->update([
                'total_records' => $recordCount,
                'file_size_bytes' => $fileSize,
                'storage_path' => $s3Path,
                'storage_type' => $storage,
                'checksum_sha256' => $checksum,
                'duration_seconds' => $duration,
            ]);
            $backup->markAsCompleted($duration, $checksum);

            Log::info('RUC backup completed successfully', [
                'backup_id' => $backup->id,
                'file_size' => $this->formatBytes($fileSize),
                'records' => $recordCount,
                'duration' => $duration . 's',
            ]);

            return $backup;

        } catch (\Throwable $e) {
            Log::error('RUC backup failed', [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            $backup->markAsFailed($e->getMessage());
            @unlink($localPath);

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

        Log::info('Starting RUC database restore', [
            'backup_id' => $backup->id,
            'dry_run' => $dryRun,
        ]);

        $startTime = microtime(true);

        try {
            // 1. Descargar archivo si está en S3
            $filePath = $backup->storage_path;
            if ($backup->storage_type === 's3') {
                $filePath = $this->downloadFromS3($backup->storage_path);
            }

            // 2. Validar checksum
            if ($backup->checksum_sha256) {
                $calculatedChecksum = hash_file('sha256', $filePath);
                if ($calculatedChecksum !== $backup->checksum_sha256) {
                    throw new \Exception('Checksum validation failed - backup may be corrupted');
                }
            }

            // 3. Restaurar en BD
            if (!$dryRun) {
                // Backup actual antes de restaurar
                $safetyBackup = $this->backup('safety_before_restore');
                Log::warning('Safety backup created before restore', ['backup_id' => $safetyBackup->id]);

                $this->restoreFromDump($filePath, $backup);
            }

            $duration = intval(microtime(true) - $startTime);

            Log::info('RUC database restore completed', [
                'backup_id' => $backup->id,
                'duration' => $duration . 's',
                'dry_run' => $dryRun,
            ]);

            // Limpiar archivo temporal si fue descargado
            if ($backup->storage_type === 's3') {
                @unlink($filePath);
            }

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

        // Usar pg_dump con compresión y paralelización
        $command = [
            'pg_dump',
            '--host=' . $dbHost,
            '--port=' . $dbPort,
            '--username=' . $dbUser,
            '--table=ruc_records',
            '--compress=' . self::COMPRESSION_LEVEL,
            '--format=custom',  // Format personalizado para mejor compresión
            '--file=' . $outputPath,
            $dbName,
        ];

        $process = new Process($command);
        $process->setTimeout(3600); // 1 hora de timeout
        $process->setEnv(['PGPASSWORD' => config('database.connections.pgsql.password')]);

        $process->mustRun();

        if (!file_exists($outputPath)) {
            throw new \Exception('Dump file was not created');
        }
    }

    /**
     * Restaurar desde dump
     */
    private function restoreFromDump(string $filePath, RucBackup $backup): void
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
     * Subir a S3
     */
    private function uploadToS3(string $filePath, string $fileName): string
    {
        $disk = Storage::disk('s3');
        $path = 'ruc-backups/' . $fileName;

        // Subir con streaming (para archivos grandes)
        $stream = fopen($filePath, 'r');
        $disk->put($path, $stream, ['ContentType' => 'application/gzip']);
        @fclose($stream);

        Log::info('Backup uploaded to S3', ['path' => $path]);

        return $path;
    }

    /**
     * Descargar de S3
     */
    private function downloadFromS3(string $s3Path): string
    {
        $disk = Storage::disk('s3');
        $localPath = self::BACKUP_DIR . '/' . basename($s3Path);

        // Descargar con streaming
        $stream = fopen($localPath, 'w');
        $s3Stream = $disk->readStream($s3Path);
        stream_copy_to_stream($s3Stream, $stream);
        @fclose($stream);
        @fclose($s3Stream);

        return $localPath;
    }

    /**
     * Verificar si S3 está habilitado
     */
    private function isS3Enabled(): bool
    {
        return config('filesystems.disks.s3.key')
            && config('filesystems.disks.s3.secret');
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
                if ($backup->storage_type === 's3') {
                    Storage::disk('s3')->delete($backup->storage_path);
                } else {
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
