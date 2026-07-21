<?php

namespace App\Modules\Reniec\Services;

use Illuminate\Support\Facades\DB;

final class ReniecCopyLoader
{
    public function load(int $importId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        if (DB::getDriverName() !== 'pgsql') {
            DB::table('reniec_import_staging')->insertOrIgnore(array_map(fn ($r) => ['import_id' => $importId] + $r, $rows));

            return;
        }
        $lines = array_map(function ($r) use ($importId) {
            $values = [$importId, $r['row_number'], $r['dni'], $r['nombres'], $r['apellido_paterno'], $r['apellido_materno'], $r['fecha_nacimiento'], $r['genero'], $r['ubigeo']];

            return implode("\t", array_map(fn ($v) => $v === null ? '\\N' : str_replace(['\\', "\t", "\n", "\r"], ['\\\\', '\\t', '\\n', '\\r'], (string) $v), $values));
        }, $rows);
        $pdo = DB::connection()->getPdo();
        if (! method_exists($pdo, 'pgsqlCopyFromArray')) {
            throw new \RuntimeException('PDO PostgreSQL no soporta COPY FROM STDIN.');
        }
        $pdo->pgsqlCopyFromArray('reniec_import_staging', $lines, "\t", '\\N', 'import_id,row_number,dni,nombres,apellido_paterno,apellido_materno,fecha_nacimiento,genero,ubigeo');
    }
}
