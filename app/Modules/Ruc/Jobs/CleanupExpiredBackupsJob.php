<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Jobs;

use App\Modules\Ruc\Services\RucBackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class CleanupExpiredBackupsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 3600; // 1 hora

    public int $tries = 1;

    public function handle(): void
    {
        Log::info('Limpiando backups expirados...');
        (new RucBackupService())->cleanupExpiredBackups();
        Log::info('Limpieza de backups completada');
    }
}
