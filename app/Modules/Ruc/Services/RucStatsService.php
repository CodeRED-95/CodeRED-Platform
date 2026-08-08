<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RucStatsService
{
    private const CACHE_KEY = 'ruc:stats:total_records';
    private const CACHE_TTL = 86400; // 24 horas

    /**
     * Obtener total de registros RUC.
     * Preferencia: ruc_statistics table → estimation → NULL.
     * NUNCA hace COUNT(*) sobre ruc_records en listado normal.
     */
    public function getTotalRecords(): int
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): int {
            $stats = DB::table('ruc_statistics')->first();
            if ($stats) {
                return (int) $stats->total_records;
            }

            // Fallback: estimación PostgreSQL (no COUNT exacto)
            return $this->estimatedTotal();
        });
    }

    /**
     * Establecer total manualmente (después de import/restore).
     */
    public function setTotalRecords(int $count): void
    {
        DB::table('ruc_statistics')->updateOrInsert(
            ['id' => 1],
            [
                'total_records' => $count,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Invalidar cache inmediatamente
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Estimar total usando pg_class.reltuples (sin COUNT).
     * Muy rápido, aproximado pero suficiente para UX.
     */
    public function estimatedTotal(): int
    {
        $result = DB::selectOne(
            "SELECT reltuples::bigint as estimate FROM pg_class WHERE oid = 'ruc_records'::regclass"
        );

        return max((int) ($result->estimate ?? 0), 0);
    }

    /**
     * Invalidar cache (llamar después de import/restore completado).
     */
    public function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
