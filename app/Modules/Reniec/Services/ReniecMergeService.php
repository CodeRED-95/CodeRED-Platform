<?php

namespace App\Modules\Reniec\Services;

use App\Modules\Reniec\Models\ReniecImport;
use Illuminate\Support\Facades\DB;

final class ReniecMergeService
{
    public function merge(ReniecImport $import): array
    {
        if (! in_array($import->strategy, ['insert_ignore', 'upsert'], true)) {
            throw new \InvalidArgumentException('Estrategia RENIEC no soportada.');
        }
        $before = DB::table('dni_records')->count();
        $select = "SELECT s.dni, concat_ws(' ',s.nombres,s.apellido_paterno,s.apellido_materno),s.nombres,s.apellido_paterno,s.apellido_materno,s.genero,s.fecha_nacimiento,'import',s.ubigeo,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP FROM (SELECT *, row_number() OVER (PARTITION BY dni ORDER BY row_number DESC) AS duplicate_rank FROM reniec_import_staging WHERE import_id = ?) s WHERE s.duplicate_rank = 1";
        $columns = 'dni,nombre_completo,nombres,apellido_paterno,apellido_materno,genero,fecha_nacimiento,source,ubigeo,created_at,updated_at';
        if ($import->strategy === 'insert_ignore') {
            DB::statement("INSERT INTO dni_records ($columns) $select ON CONFLICT (dni) DO NOTHING", [$import->id]);
        } else {
            DB::statement("INSERT INTO dni_records ($columns) $select ON CONFLICT (dni) DO UPDATE SET nombre_completo=EXCLUDED.nombre_completo,nombres=EXCLUDED.nombres,apellido_paterno=EXCLUDED.apellido_paterno,apellido_materno=EXCLUDED.apellido_materno,genero=EXCLUDED.genero,fecha_nacimiento=EXCLUDED.fecha_nacimiento,ubigeo=EXCLUDED.ubigeo,source='import',updated_at=CURRENT_TIMESTAMP", [$import->id]);
        }
        $after = DB::table('dni_records')->count();
        $inserted = $after - $before;
        $valid = DB::table('reniec_import_staging')->where('import_id', $import->id)->distinct()->count('dni');

        return ['inserted' => $inserted, 'updated' => $import->strategy === 'upsert' ? max(0, $valid - $inserted) : 0, 'ignored' => $import->strategy === 'insert_ignore' ? max(0, $valid - $inserted) : 0];
    }
}
