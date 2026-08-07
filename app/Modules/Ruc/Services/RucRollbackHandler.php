<?php

namespace App\Modules\Ruc\Services;

use App\Models\User;
use App\Modules\Ruc\Data\RollbackResult;
use App\Modules\Ruc\Enums\RucImportStatusV3;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Support\Facades\DB;

class RucRollbackHandler
{
    /**
     * Revierte una importación completamente
     */
    public function rollback(
        RucImport $import,
        ?User $initiatedBy = null,
        ?string $reason = null
    ): RollbackResult {
        $result = new RollbackResult;

        // Validar que se puede hacer rollback
        if (! $import->canRollback()) {
            $result->success = false;
            $result->message = 'Esta importación no puede ser revertida. Solo completadas o con errores.';

            return $result;
        }

        try {
            DB::transaction(function () use ($import, &$result, $initiatedBy, $reason) {
                // Actualizar status a "rolling back"
                $import->update([
                    'status' => RucImportStatusV3::RollingBack->value,
                    'rollback_started_at' => now(),
                ]);

                $import->recordEvent('import.rollback_started', [
                    'initiated_by' => auth()->id(),
                ], $initiatedBy);

                // Borrar únicamente los registros que esta importación insertó
                // originalmente (ruc_import_id). No se usa una ventana de
                // tiempo: eso podía arrastrar registros de importaciones
                // concurrentes. ON CONFLICT nunca reescribe ruc_import_id
                // (ver RucBatchInserter), así que la columna siempre refleja
                // quién creó el registro, no quién lo tocó por última vez.
                $deleted = DB::table('ruc_records')
                    ->where('ruc_import_id', $import->id)
                    ->delete();

                $result->recordsDeleted = $deleted;

                // Marcar como rolled back
                $import->update([
                    'status' => RucImportStatusV3::RolledBack->value,
                    'rollback_completed_at' => now(),
                    'rollback_reason' => $reason,
                    'inserted_rows' => 0,
                    'updated_rows' => 0,
                ]);

                $import->recordEvent('import.rollback_completed', [
                    'records_deleted' => $result->recordsDeleted,
                    'reason' => $reason,
                ], $initiatedBy);

                $result->success = true;
                $result->message = "Rollback completado. {$result->recordsDeleted} registros eliminados.";
            });
        } catch (\Exception $e) {
            $result->success = false;
            $result->message = 'Error durante rollback: '.$e->getMessage();

            // Registrar error
            $import->update([
                'status' => RucImportStatusV3::Failed->value,
                'last_error' => substr($e->getMessage(), 0, 500),
            ]);

            $import->recordEvent('import.failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Simula un rollback sin ejecutarlo (dry-run)
     */
    public function dryRun(RucImport $import): RollbackResult
    {
        $result = new RollbackResult;

        if (! $import->canRollback()) {
            $result->success = false;
            $result->message = 'Esta importación no puede ser revertida.';

            return $result;
        }

        // Contar cuántos registros se borrarían (misma condición que rollback())
        $result->recordsDeleted = DB::table('ruc_records')
            ->where('ruc_import_id', $import->id)
            ->count();

        $result->success = true;
        $result->message = "Dry-run: Se borraría {$result->recordsDeleted} registros.";

        return $result;
    }
}
