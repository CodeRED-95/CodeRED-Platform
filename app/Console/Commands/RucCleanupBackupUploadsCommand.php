<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use Illuminate\Console\Command;

/**
 * Sesiones de subida multipart (manifest.json + partes de RUC Tools) que
 * quedaron pendientes/incompletas más allá de su expiración (por defecto
 * 24h, ver config('ruc.backup.multipart.session_expires_hours')): se
 * cancelan y se borran sus partes temporales. NUNCA toca uploads
 * completados ni los RucBackup ya ensamblados.
 */
class RucCleanupBackupUploadsCommand extends Command
{
    protected $signature = 'ruc:cleanup-backup-uploads';

    protected $description = 'Cancela y limpia sesiones de subida multipart de backups RUC expiradas';

    public function handle(RucBackupMultipartUploadService $service): int
    {
        $count = $service->cleanupExpired();
        $this->info("Sesiones de subida expiradas limpiadas: {$count}.");

        return self::SUCCESS;
    }
}
