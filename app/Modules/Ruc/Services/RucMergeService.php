<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Models\RucImport;
use Illuminate\Support\Facades\DB;

final class RucMergeService
{
    public function merge(RucImport $import): array
    {
        $valid = DB::table('ruc_staging')->where('import_id', $import->id)->count();
        if (DB::getDriverName() === 'pgsql') {
            $result = DB::selectOne(<<<'SQL'
                WITH ranked AS (
                    SELECT *, row_number() OVER (PARTITION BY ruc ORDER BY row_number) AS duplicate_rank
                    FROM ruc_staging WHERE import_id = ?
                ), inserted AS (
                    INSERT INTO ruc_records
                        (ruc, razon_social, estado, condicion, ubigeo, departamento, provincia, distrito, direccion, created_at, updated_at)
                    SELECT ruc, razon_social, estado, condicion, ubigeo, departamento, provincia, distrito, direccion, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    FROM ranked WHERE duplicate_rank = 1
                    ON CONFLICT (ruc) DO NOTHING
                    RETURNING ruc
                ) SELECT count(*)::bigint AS aggregate FROM inserted
                SQL, [$import->id]);
            $inserted = (int) ($result->aggregate ?? 0);
        } else {
            $inserted = 0;
            DB::table('ruc_staging')->where('import_id', $import->id)->orderBy('row_number')->get()->unique('ruc')->each(function ($row) use (&$inserted): void {
                $inserted += DB::table('ruc_records')->insertOrIgnore([
                    'ruc' => $row->ruc, 'razon_social' => $row->razon_social, 'estado' => $row->estado,
                    'condicion' => $row->condicion, 'ubigeo' => $row->ubigeo, 'departamento' => $row->departamento,
                    'provincia' => $row->provincia, 'distrito' => $row->distrito, 'direccion' => $row->direccion,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        }

        return ['inserted' => $inserted, 'ignored' => max(0, $valid - $inserted), 'valid' => $valid];
    }
}
