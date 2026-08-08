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
        if (! Storage::disk('local')->exists(self::BACKUP_DIR)) {
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
        // Sufijo aleatorio: evita colisión si dos backups se disparan dentro
        // del mismo segundo (p. ej. el "safety_before_restore" automático).
        $backupName = "ruc_backup_{$timestamp}_".bin2hex(random_bytes(4)).'.sql.gz';
        $localPath = storage_path('app/'.self::BACKUP_DIR.'/'.$backupName);

        try {
            Log::info('Starting RUC database backup', ['backup_type' => $backupType, 'user_id' => $user?->id]);

            // 1. Crear dump de la tabla ruc_records PRIMERO
            $this->createDump($localPath);

            // 2. Validar que el archivo fue creado
            if (! file_exists($localPath)) {
                throw new \Exception('Dump file was not created at '.$localPath);
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
                'duration' => $duration.'s',
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

        if (! file_exists($backup->storage_path)) {
            throw new \Exception('El archivo de backup no existe: '.$backup->storage_path);
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
            if (! $dryRun) {
                // Validar que el archivo es un dump de pg_restore válido ANTES
                // de tocar la base de datos (el restore hace TRUNCATE primero).
                $this->validateDumpFile($backup->storage_path);

                // Backup actual antes de restaurar
                $safetyBackup = $this->backup('safety_before_restore');
                Log::warning('Safety backup created before restore', ['backup_id' => $safetyBackup->id]);

                try {
                    $this->restoreFromDump($backup->storage_path);
                } catch (\Throwable $e) {
                    Log::error('Restore failed after truncating ruc_records; attempting automatic recovery from safety backup', [
                        'backup_id' => $backup->id,
                        'safety_backup_id' => $safetyBackup->id,
                        'error' => $e->getMessage(),
                    ]);

                    try {
                        $this->restoreFromDump($safetyBackup->storage_path);
                        Log::warning('Automatic recovery from safety backup succeeded', ['safety_backup_id' => $safetyBackup->id]);
                    } catch (\Throwable $recoveryError) {
                        Log::error('Automatic recovery from safety backup FAILED - ruc_records may be empty', [
                            'safety_backup_id' => $safetyBackup->id,
                            'error' => $recoveryError->getMessage(),
                        ]);
                    }

                    throw $e;
                }
            }

            $duration = intval(microtime(true) - $startTime);

            Log::info('RUC database restore completed', [
                'backup_id' => $backup->id,
                'duration' => $duration.'s',
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
        if (! is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // Usar pg_dump con compresión
        $command = [
            'pg_dump',
            '--host='.$dbHost,
            '--port='.$dbPort,
            '--username='.$dbUser,
            '--table=ruc_records',
            '--compress='.self::COMPRESSION_LEVEL,
            '--format=custom',
            '--file='.$outputPath,
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
            throw new \Exception('Failed to create backup dump: '.$process->getErrorOutput());
        }

        if (! file_exists($outputPath)) {
            throw new \Exception('Dump file was not created at '.$outputPath);
        }

        if (filesize($outputPath) === 0) {
            @unlink($outputPath);
            throw new \Exception('Dump file is empty - no records to backup or pg_dump failed');
        }
    }

    /**
     * Verifica que el archivo sea un dump válido en formato "custom" de
     * pg_dump/pg_restore antes de dejarlo usar en un restore. `pg_restore
     * --list` solo lee la tabla de contenidos del archivo, no toca la BD.
     * Público para poder validarlo también al momento de subir un archivo
     * (antes de aceptarlo como backup), no solo al restaurar.
     */
    public function validateDumpFile(string $filePath): void
    {
        if (! file_exists($filePath) || filesize($filePath) === 0) {
            throw new \Exception('El archivo de backup no existe o está vacío: '.$filePath);
        }

        $process = new Process(['pg_restore', '--list', $filePath]);
        $process->setTimeout(120);

        try {
            $process->mustRun();
        } catch (\Throwable $e) {
            throw new \Exception('El archivo de backup no es un dump válido de PostgreSQL (formato custom de pg_dump): '.$process->getErrorOutput());
        }
    }

    /**
     * Restaurar desde dump
     */
    private function restoreFromDump(string $filePath): void
    {
        $this->validateDumpFile($filePath);

        $dbName = config('database.connections.pgsql.database');
        $dbUser = config('database.connections.pgsql.username');
        $dbHost = config('database.connections.pgsql.host');
        $dbPort = config('database.connections.pgsql.port', 5432);

        // NO hacemos TRUNCATE manual aquí. El dump incluye el CREATE TABLE
        // completo (pg_dump --table=ruc_records sin --data-only), así que
        // un TRUNCATE previo no sirve: la tabla sigue existiendo y
        // pg_restore falla con "relation already exists". La forma correcta
        // es --clean --if-exists (DROP + CREATE dentro del propio dump) +
        // --single-transaction: si algo falla a mitad de camino, Postgres
        // revierte TODO automáticamente y ruc_records queda exactamente
        // como estaba antes — no hace falta truncar por fuera ni arriesgarse
        // a dejar la tabla vacía si pg_restore aborta.
        //
        // --single-transaction y --jobs son además mutuamente excluyentes en
        // pg_restore (el restore paralelo usa varias conexiones, incompatible
        // con una sola transacción), así que tampoco se puede paralelizar.
        $command = [
            'pg_restore',
            '--host='.$dbHost,
            '--port='.$dbPort,
            '--username='.$dbUser,
            '--dbname='.$dbName,
            '--clean',               // DROP de los objetos existentes antes de recrearlos
            '--if-exists',           // no fallar si algo ya no existe
            '--single-transaction',  // todo o nada
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

        return round($size, 2).' '.$units[$i];
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
