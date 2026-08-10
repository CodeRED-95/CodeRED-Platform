<?php

declare(strict_types=1);

namespace App\Modules\Agencies\Jobs;

use App\Modules\Agencies\Enums\AgencyRestoreStatus;
use App\Modules\Agencies\Models\AgencyBackupRestore;
use App\Modules\Agencies\Services\AgencyRestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Ejecuta la restauración fuera del ciclo petición/respuesta.
 *
 * La interfaz solo encola y consulta el progreso por sondeo, así que una copia
 * grande no puede provocar un timeout de Cloudflare ni de PHP.
 */
class RestoreAgencyBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  int  $restoreId  registro de restauración a procesar
     * @param  int|null  $restoredByUserId  usuario autenticado que lanzó la
     *                                      restauración. Se pasa explícitamente porque en el worker no hay
     *                                      sesión: es el sustituto de created_by/updated_by cuando el id del
     *                                      backup no existe en el destino.
     */
    public function __construct(public int $restoreId, public ?int $restoredByUserId = null) {}

    /** @return array<int, object> */
    public function middleware(): array
    {
        // Dos restauraciones simultáneas escribirían sobre las mismas filas.
        return [(new WithoutOverlapping('agency-backup-restore'))->expireAfter(3600)];
    }

    public function handle(AgencyRestoreService $service): void
    {
        $restore = AgencyBackupRestore::query()->find($this->restoreId);

        if ($restore === null || $restore->status->isFinished()) {
            return;
        }

        $service->restore($restore, $this->restoredByUserId);
    }

    public function failed(Throwable $exception): void
    {
        $restore = AgencyBackupRestore::query()->find($this->restoreId);

        if ($restore === null || $restore->status->isFinished()) {
            return;
        }

        // Si el proceso muere sin pasar por el catch del servicio (memoria,
        // timeout, worker reiniciado), la fila no puede quedarse en "pendiente".
        $restore->forceFill([
            'status' => AgencyRestoreStatus::Failed,
            'stage' => 'Fallida',
            'error_message' => 'La restauración se interrumpió antes de terminar. Revisa los registros del sistema.',
            'finished_at' => now(),
        ])->save();
    }
}
