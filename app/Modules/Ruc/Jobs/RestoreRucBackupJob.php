<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Jobs;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Services\RucBackupService;
use App\Modules\Ruc\Services\RucStatisticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ejecuta un restore de ruc_records en segundo plano, en la cola/worker
 * DEDICADOS "ruc-backups" (nunca comparte cola con agency-imports/
 * ruc-imports/default): un restore de millones de filas puede tardar
 * decenas de minutos u horas y no debe bloquear nada más, ni verse
 * bloqueado por nada más. El controller SOLO crea el RucBackupOperation y
 * despacha este Job — nunca ejecuta pg_dump/psql dentro del request HTTP
 * (esa era la causa raíz del 524 de Cloudflare). Ver
 * RucBackupController::restore().
 *
 * $tries=1 es deliberado: un restore reintentado automáticamente por
 * Laravel mientras el intento anterior sigue vivo (p. ej. por un
 * retry_after mal configurado) haría DOS procesos psql compitiendo por
 * TRUNCATE la misma tabla. La atomicidad de restoreDataAtomically() ya
 * garantiza que un fallo deja ruc_records intacta — no hay ganancia en
 * reintentar automáticamente una operación destructiva, solo riesgo.
 */
class RestoreRucBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $operationId)
    {
        $this->timeout = max(3600, (int) config('ruc.backup.restore.timeout', 86400));
        $this->onConnection('ruc-backups');
        $this->onQueue((string) config('ruc.backup.restore.queue', 'ruc-backups'));
    }

    public function handle(RucBackupService $service): void
    {
        $operation = RucBackupOperation::findOrFail($this->operationId);
        $backup = RucBackup::findOrFail($operation->backup_id);

        // Mismo mutex que el comando CLI `ruc:restore` (RucBackupService::
        // acquireRestoreLock): sin importar el origen, dos restores nunca
        // deben correr a la vez.
        $lock = $service->acquireRestoreLock();

        try {
            $this->run($service, $operation, $backup);
        } catch (Throwable $e) {
            $this->markFailed($operation, $e);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function run(RucBackupService $service, RucBackupOperation $operation, RucBackup $backup): void
    {
        $startedAt = microtime(true);

        $operation->update([
            'status' => RucBackupOperation::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        Log::info('Starting RUC restore (job)', [
            'operation_id' => $operation->id,
            'backup_id' => $backup->id,
            'performed_by' => $operation->created_by,
        ]);

        $this->step($operation, RucBackupOperation::STAGE_VALIDATING_BACKUP, 5, 'Validando backup');
        $service->validateBackup($backup);

        $this->step($operation, RucBackupOperation::STAGE_VERIFYING_CHECKSUM, 10, 'Verificando checksum');
        $service->verifyChecksum($backup);

        $recordsBefore = $service->countRucRecords();
        $operation->update(['records_before' => $recordsBefore]);
        Log::info('RUC restore (job): records before safety backup', [
            'operation_id' => $operation->id,
            'backup_id' => $backup->id,
            'records_before_safety' => $recordsBefore,
        ]);

        $this->step($operation, RucBackupOperation::STAGE_CREATING_SAFETY_BACKUP, 20, 'Creando Safety Backup');
        try {
            $safetyBackup = $service->createSafetyBackup($operation->createdBy);
        } catch (Throwable $e) {
            throw new \RuntimeException('No se pudo crear el backup de seguridad; restore cancelado. '.$e->getMessage());
        }
        $operation->update(['safety_backup_id' => $safetyBackup->id]);

        $this->step($operation, RucBackupOperation::STAGE_VALIDATING_SAFETY_BACKUP, 35, 'Validando Safety Backup');
        $service->validateSafetyBackup($safetyBackup, $recordsBefore);

        $this->step($operation, RucBackupOperation::STAGE_PREPARING_RESTORE, 45, 'Preparando Restore');
        $service->assertSufficientDiskSpaceForRestore($backup->absolutePath());

        // Progreso honesto: dentro de restoreDataAtomically() no hay señal
        // incremental real (un solo proceso psql, una sola transacción) —
        // mantener 50% fijo en vez de inventar avance. La UI puede mostrar
        // esto como indeterminado.
        $this->step($operation, RucBackupOperation::STAGE_RESTORING, 50, 'Restaurando datos RUC');
        $service->restoreDataAtomically($backup->absolutePath());

        $this->step($operation, RucBackupOperation::STAGE_VERIFYING_RESTORE, 90, 'Verificando resultado');
        $recordsAfter = $service->countRucRecords();
        $service->verifyRestoreRecordCount($backup, $recordsAfter);

        // Etapa final: actualizar estadísticas de tabla para query planner
        // (crítico: TRUNCATE + bulk load deja pg_class.n_live_tup stale,
        // lo que causa planes de query malos durante horas sin ANALYZE).
        // Esta operación es rápida (~2-5s) y no bloquea.
        Log::info('Starting ANALYZE after RUC restore', ['operation_id' => $operation->id]);
        DB::statement('ANALYZE ruc_records');
        Log::info('ANALYZE completed after RUC restore', ['operation_id' => $operation->id]);

        // Actualizar tabla ruc_statistics (usado por dashboard, admin pages)
        // e invalidar caches. Esto permite que dashboard muestre el nuevo
        // conteo inmediatamente (próximo request), sin esperar 60s de TTL.
        app(RucStatisticsService::class)->updateAllStatistics('restore');
        app(RucStatisticsService::class)->recordAnalyzeComplete();

        $duration = (int) round(microtime(true) - $startedAt);

        $operation->update([
            'status' => RucBackupOperation::STATUS_COMPLETED,
            'stage' => RucBackupOperation::STAGE_COMPLETED,
            'progress' => 100,
            'message' => 'Completado',
            'records_after' => $recordsAfter,
            'duration_seconds' => $duration,
            'finished_at' => now(),
        ]);

        Log::info('RUC restore completed (job)', [
            'operation_id' => $operation->id,
            'backup_id' => $backup->id,
            'safety_backup_id' => $safetyBackup->id,
            'records_before' => $recordsBefore,
            'records_after' => $recordsAfter,
            'duration_seconds' => $duration,
        ]);
    }

    private function step(RucBackupOperation $operation, string $stage, int $progress, string $message): void
    {
        $operation->update(['stage' => $stage, 'progress' => $progress, 'message' => $message]);
    }

    /**
     * status=failed conserva el último "progress"/"stage" alcanzado (no se
     * reinicia a 0): la UI debe poder mostrar en qué etapa falló.
     */
    private function markFailed(RucBackupOperation $operation, Throwable $e): void
    {
        $operation->forceFill([
            'status' => RucBackupOperation::STATUS_FAILED,
            'error_message' => substr($e->getMessage(), 0, 1000),
            'finished_at' => now(),
        ])->save();

        Log::error('RUC restore failed (job)', [
            'operation_id' => $operation->id,
            'backup_id' => $operation->backup_id,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Cubre el caso en que Laravel mata el worker por timeout (86400s): esa
     * terminación no pasa por el catch de handle() en el mismo proceso —
     * failed() es el único hook garantizado en ese escenario.
     */
    public function failed(Throwable $exception): void
    {
        if (($operation = RucBackupOperation::find($this->operationId)) !== null) {
            $this->markFailed($operation, $exception);
        }
    }
}
