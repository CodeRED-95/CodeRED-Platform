<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Models\RucImportDuplicate;
use App\Modules\Ruc\Models\RucImportError;
use Illuminate\Support\Facades\DB;

class RucErrorRecorder
{
    /**
     * Registra un lote de errores de forma eficiente
     */
    public function recordErrors(RucImport $import, array $errors): void
    {
        if (empty($errors)) {
            return;
        }

        $toInsert = [];
        foreach ($errors as $error) {
            $toInsert[] = [
                'ruc_import_id' => $import->id,
                'line_number' => $error['line'] ?? 0,
                'error_code' => $error['code'] ?? 'UNKNOWN',
                'error_category' => $error['category'] ?? 'validation',
                'reason' => $error['reason'] ?? implode('; ', $error['errors'] ?? []),
                'line_preview' => $error['preview'] ?? null,
                'created_at' => now(),
            ];
        }

        // Batch insert
        foreach (array_chunk($toInsert, 1000) as $chunk) {
            DB::table('ruc_import_errors')->insert($chunk);
        }
    }

    /**
     * Registra un lote de duplicados
     */
    public function recordDuplicates(RucImport $import, array $duplicates): void
    {
        if (empty($duplicates)) {
            return;
        }

        $toInsert = [];
        foreach ($duplicates as $dup) {
            $toInsert[] = [
                'ruc_import_id' => $import->id,
                'ruc' => $dup['ruc'],
                'first_line' => $dup['first_line'],
                'duplicate_line' => $dup['duplicate_line'],
                'action' => $dup['action'] ?? 'skipped',
                'created_at' => now(),
            ];
        }

        // Batch insert
        foreach (array_chunk($toInsert, 1000) as $chunk) {
            DB::table('ruc_import_duplicates')->insert($chunk);
        }
    }

    /**
     * Obtiene un resumen de errores
     */
    public function getErrorSummary(RucImport $import): array
    {
        $errors = DB::table('ruc_import_errors')
            ->where('ruc_import_id', $import->id)
            ->select('error_category', DB::raw('count(*) as count'))
            ->groupBy('error_category')
            ->get()
            ->pluck('count', 'error_category')
            ->toArray();

        return $errors;
    }

    /**
     * Obtiene un resumen de duplicados
     */
    public function getDuplicateSummary(RucImport $import): array
    {
        return [
            'total_duplicates' => DB::table('ruc_import_duplicates')
                ->where('ruc_import_id', $import->id)
                ->count(),
            'skipped' => DB::table('ruc_import_duplicates')
                ->where('ruc_import_id', $import->id)
                ->where('action', 'skipped')
                ->count(),
        ];
    }
}
