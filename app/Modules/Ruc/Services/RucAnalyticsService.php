<?php

namespace App\Modules\Ruc\Services;

use Illuminate\Support\Facades\DB;

class RucAnalyticsService
{
    public function analyzeRucRecords(): void
    {
        // Execute ANALYZE ruc_records
        // This updates statistics in pg_stat_user_tables
        // Critical after large imports/restores (~5-10s for 18M rows)
        // Criteria 33-34: Automate ANALYZE post-import/restore
        DB::statement('ANALYZE ruc_records');
    }

    public function analyzeRelatedTables(): void
    {
        // ANALYZE indices and related tables
        DB::statement('ANALYZE ruc_backups');
        DB::statement('ANALYZE ruc_backup_operations');
    }

    public function analyzeAll(): void
    {
        // Full ANALYZE of public schema
        DB::statement('ANALYZE');
    }

    public function getTableStats(): array
    {
        // Return current statistics for debugging/monitoring
        // Criteria 26-28: Performance measurement validation
        return DB::table('pg_stat_user_tables')
            ->select([
                'relname',
                'n_live_tup',
                'n_dead_tup',
                'n_tup_ins',
                'n_tup_upd',
                'n_tup_del',
                'last_vacuum',
                'last_analyze',
            ])
            ->whereIn('relname', ['ruc_records', 'ruc_backups', 'ruc_backup_operations'])
            ->get()
            ->toArray();
    }

    public function getIndexStats(): array
    {
        // Return index statistics (sizes, scan counts)
        return DB::table('pg_stat_user_indexes')
            ->select([
                'relname',
                'indexrelname',
                'idx_scan',
                'idx_tup_read',
                'idx_tup_fetch',
            ])
            ->whereIn('relname', ['ruc_records'])
            ->get()
            ->toArray();
    }

    public function getQueryPerformance(string $query): array
    {
        // Execute EXPLAIN ANALYZE on a query and return results
        // Used for PHASE 5: Performance measurement
        $result = DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.$query);

        $payload = $result[0]->{'QUERY PLAN'} ?? $result[0]->{'query plan'} ?? null;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? ($decoded[0] ?? $decoded) : [];
        }

        return json_decode(json_encode($result[0]), true);
    }
}
