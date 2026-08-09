<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mantiene ruc_statistics actualizado después de un restore. Esto permite
 * que el dashboard, admin pages y endpoints públicos no ejecuten COUNT(*)
 * costosos sobre 18M+ filas.
 *
 * Invocado por RestoreRucBackupJob al completar.
 */
class RucStatisticsService
{
    /**
     * Actualiza ruc_statistics con conteos reales (post-restore) e invalida
     * caches.
     *
     * Debe ejecutarse DENTRO del Job que completó la operación (en la cola,
     * no en el request HTTP) — así no bloquea el usuario.
     */
    public function updateAllStatistics(string $operationType = 'manual'): array
    {
        $totalRecords = DB::table('ruc_records')->count();
        $now = now();

        $updateData = [
            'total_records' => $totalRecords,
            'updated_at' => $now,
        ];

        if ($operationType === 'restore') {
            $updateData['last_restore_at'] = $now;
        }

        // Actualizar tabla (debe existir por migration)
        DB::table('ruc_statistics')->update($updateData);

        // Invalidar caches que dependían de estos valores
        Cache::forget('ruc:records:count');
        Cache::forget('dashboard:ruc');

        Log::info('RUC statistics updated', [
            'total_records' => $totalRecords,
            'operation' => $operationType,
        ]);

        return [
            'total_records' => $totalRecords,
        ];
    }

    /**
     * Llamado después de que ANALYZE completa. Registra el timestamp para
     * que el sistema pueda monitorear si las estadísticas de la tabla están
     * frescas (importantes para el query planner).
     */
    public function recordAnalyzeComplete(): void
    {
        DB::table('ruc_statistics')->update(['last_analyzed_at' => now()]);

        Log::info('ANALYZE recorded in ruc_statistics');
    }
}
