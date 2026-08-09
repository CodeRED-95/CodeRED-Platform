<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Services\RucChunkedRestoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones de control sobre una restauración troceada: consultar estado,
 * pedir cancelación, descartar el staging o revertir un swap ya hecho.
 *
 * Va aparte de `ruc:restore` a propósito: son acciones sobre una operación
 * que YA existe, no formas de lanzar una nueva. Mezclarlas en el mismo
 * comando obligaría a que `ruc:restore` aceptara invocaciones sin backup.
 */
class RucRestoreManageCommand extends Command
{
    protected $signature = 'ruc:restore-manage
        {--status : Muestra el estado de la última restauración y del staging}
        {--cancel : Solicita cancelar la restauración en curso tras el lote actual}
        {--discard-staging : Elimina ruc_records_next (se pierde el progreso reanudable)}
        {--rollback : Devuelve ruc_records_old al puesto activo}';

    protected $description = 'Consultar, cancelar, descartar o revertir una restauración RUC troceada';

    public function handle(): int
    {
        $service = app(RucChunkedRestoreService::class);

        return match (true) {
            (bool) $this->option('cancel') => $this->cancel(),
            (bool) $this->option('discard-staging') => $this->discardStaging($service),
            (bool) $this->option('rollback') => $this->rollback($service),
            default => $this->status($service),
        };
    }

    private function status(RucChunkedRestoreService $service): int
    {
        $operation = RucBackupOperation::query()
            ->where('operation_type', RucBackupOperation::TYPE_RESTORE)
            ->latest('id')
            ->first();

        $rows = [
            ['ruc_records', $service->tableExists(RucChunkedRestoreService::ACTIVE_TABLE)
                ? number_format($this->rowCount(RucChunkedRestoreService::ACTIVE_TABLE)).' registros'
                : 'NO EXISTE'],
            [RucChunkedRestoreService::STAGING_TABLE, $service->tableExists(RucChunkedRestoreService::STAGING_TABLE)
                ? number_format($this->rowCount(RucChunkedRestoreService::STAGING_TABLE)).' registros (restauración en curso o interrumpida)'
                : '—'],
            [RucChunkedRestoreService::OLD_TABLE, $service->tableExists(RucChunkedRestoreService::OLD_TABLE)
                ? number_format($this->rowCount(RucChunkedRestoreService::OLD_TABLE)).' registros (disponible para rollback)'
                : '—'],
        ];

        $this->info('Tablas');
        $this->table(['Tabla', 'Estado'], $rows);

        if ($operation === null) {
            $this->line('No hay ninguna operación de restauración registrada.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Última operación');
        $this->table(['Propiedad', 'Valor'], [
            ['UUID', $operation->uuid],
            ['Estado', $operation->status],
            ['Etapa', $operation->stage],
            ['Lotes', ($operation->completed_batches ?? 0).'/'.($operation->total_batches ?? '?')],
            ['Registros', number_format((int) $operation->records_processed)],
            ['Progreso', $operation->progress.'%'],
            ['Reanudable', $operation->isResumable() ? 'sí (--resume)' : 'no'],
            ['Mensaje', (string) $operation->message],
            ['Error', (string) $operation->error_message ?: '—'],
        ]);

        return self::SUCCESS;
    }

    private function cancel(): int
    {
        $operation = RucBackupOperation::activeRestore();

        if ($operation === null) {
            $this->error('❌ No hay ninguna restauración en curso.');

            return self::FAILURE;
        }

        $operation->update(['cancel_requested_at' => now()]);

        $this->info('✅ Cancelación solicitada.');
        $this->line('   La restauración se detendrá tras terminar el lote actual (el COPY en curso');
        $this->line('   no se interrumpe a la mitad, para no dejar el staging inconsistente).');
        $this->line('   ruc_records no se verá afectada en ningún caso.');

        return self::SUCCESS;
    }

    private function discardStaging(RucChunkedRestoreService $service): int
    {
        if (! $service->tableExists(RucChunkedRestoreService::STAGING_TABLE)) {
            $this->info('No existe '.RucChunkedRestoreService::STAGING_TABLE.'; nada que descartar.');

            return self::SUCCESS;
        }

        $count = $this->rowCount(RucChunkedRestoreService::STAGING_TABLE);
        $this->warn('Se eliminará '.RucChunkedRestoreService::STAGING_TABLE.' ('.number_format($count).' registros).');
        $this->line('Se perderá el progreso reanudable. ruc_records NO se toca.');

        if (! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        DB::statement('DROP TABLE IF EXISTS '.RucChunkedRestoreService::STAGING_TABLE.' CASCADE');
        $this->info('✅ Staging eliminado.');

        return self::SUCCESS;
    }

    private function rollback(RucChunkedRestoreService $service): int
    {
        if (! $service->tableExists(RucChunkedRestoreService::OLD_TABLE)) {
            $this->error('❌ No existe '.RucChunkedRestoreService::OLD_TABLE.': no hay nada que revertir.');

            return self::FAILURE;
        }

        $old = $this->rowCount(RucChunkedRestoreService::OLD_TABLE);
        $active = $this->rowCount(RucChunkedRestoreService::ACTIVE_TABLE);

        $this->warn('Se revertirá la última restauración:');
        $this->line('  ruc_records actual  : '.number_format($active).' registros');
        $this->line('  ruc_records_old     : '.number_format($old).' registros  → pasará a ser la activa');

        if (! $this->confirm('¿Continuar?', false)) {
            $this->info('Cancelado.');

            return self::SUCCESS;
        }

        $service->rollbackSwap();
        $this->info('✅ Rollback completado: ruc_records vuelve a tener '.number_format($old).' registros.');

        return self::SUCCESS;
    }

    private function rowCount(string $table): int
    {
        return (int) DB::table($table)->count();
    }
}
