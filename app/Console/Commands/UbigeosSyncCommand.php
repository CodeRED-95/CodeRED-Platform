<?php

namespace App\Console\Commands;

use App\Modules\Ruc\Services\UbigeoSyncService;
use Illuminate\Console\Command;
use Throwable;

class UbigeosSyncCommand extends Command
{
    protected $signature = 'ubigeos:sync {--dry-run} {--force} {--no-download} {--source=alanube}';

    protected $description = 'Sincroniza el catálogo de UBIGEO desde Alanube';

    public function handle(UbigeoSyncService $service): int
    {
        if ($this->option('source') !== 'alanube') {
            $this->error('La fuente solicitada no está soportada.');

            return self::INVALID;
        }
        try {
            $result = $service->sync((bool) $this->option('dry-run'), (bool) $this->option('no-download'), (bool) $this->option('force'));
            $this->table(['Detectados', 'Insertados', 'Actualizados', 'Ignorados'], [[$result['total'], $result['inserted'], $result['updated'], $result['ignored']]]);
            $this->info($result['dry_run'] ? 'Validación simulada completada; no se escribieron cambios.' : 'Catálogo UBIGEO sincronizado.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
