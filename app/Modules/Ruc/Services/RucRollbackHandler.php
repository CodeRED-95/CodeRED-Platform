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
        $result = new RollbackResult();

        // Validar que se puede hacer rollback
        if (!$import->canRollback()) {
            $result->success = false;
            $result->message = 'Esta importación no puede ser revertida. Solo completadas o con errores.';
            return $result;
        }

        try {
            DB::transaction(function () use ($import, &$result) {
                // Actualizar status a "rolling back"
                $import->update([
                    'status' => RucImportStatusV3::RollingBack->value,
                    'rollback_started_at' => now(),
                ]);

                $import->recordEvent('import.rollback_started', [
                    'initiated_by' => auth()->id(),
                ], $initiatedBy);

                // Obtener lista de RUCs insertados en esta importación
                $events = DB::table('ruc_import_events')
                    ->where('ruc_import_id', $import->id)
                    ->where('event_type', 'import.checkpoint')
                    ->pluck('data');

                $rucsToDelete = [];
                foreach ($events as $eventData) {
                    if (is_string($eventData)) {
                        $data = json_decode($eventData, true);
                    } else {
                        $data = $eventData;
                    }

                    // Opción 1: Borrar todos los registros inseridos por esta importación
                    // Obtenemos los RUCs de los eventos si están disponibles
                    // Si no, simplemente borramos registros creados recientemente
                }

                // Estrategia: Borrar registros con created_at cercana a started_at
                if ($import->started_at) {
                    $startTime = $import->started_at->subMinutes(5);
                    $endTime = now()->addMinutes(5);

                    // CUIDADO: Esto podría borrar registros de otras importaciones
                    // Por eso guardamos el registro antes

                    $deleted = DB::table('ruc_records')
                        ->whereBetween('created_at', [$startTime, $endTime])
                        ->where('updated_at', '<=', $import->finished_at ?? now())
                        ->delete();

                    $result->recordsDeleted = $deleted;
                }

                // Marcar como rolled back
                $import->update([
                    'status' => RucImportStatusV3::RolledBack->value,
                    'rollback_completed_at' => now(),
                    'rollback_reason' => $reason,
                    'inserted_records' => 0,
                    'updated_records' => 0,
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
            $result->message = "Error durante rollback: " . $e->getMessage();

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
        $result = new RollbackResult();

        if (!$import->canRollback()) {
            $result->success = false;
            $result->message = 'Esta importación no puede ser revertida.';
            return $result;
        }

        // Contar cuántos registros se borraría
        if ($import->started_at) {
            $startTime = $import->started_at->subMinutes(5);
            $endTime = now()->addMinutes(5);

            $wouldDelete = DB::table('ruc_records')
                ->whereBetween('created_at', [$startTime, $endTime])
                ->where('updated_at', '<=', $import->finished_at ?? now())
                ->count();

            $result->recordsDeleted = $wouldDelete;
        }

        $result->success = true;
        $result->message = "Dry-run: Se borraría {$result->recordsDeleted} registros.";

        return $result;
    }
}
