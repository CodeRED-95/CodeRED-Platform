<?php

namespace App\Modules\Ruc\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RucCopyLoader
{
    private const COLUMNS = ['import_id', 'row_number', 'ruc', 'razon_social', 'estado', 'condicion', 'ubigeo', 'departamento', 'provincia', 'distrito', 'direccion', 'created_at', 'updated_at'];

    public function load(int $importId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $now = now()->format('Y-m-d H:i:s');
        $records = array_map(fn (array $row): array => [
            'import_id' => $importId,
            'row_number' => $row['row_number'],
            'ruc' => $row['ruc'],
            'razon_social' => $row['razon_social'],
            'estado' => $row['estado'],
            'condicion' => $row['condicion'],
            'ubigeo' => $row['ubigeo'],
            'departamento' => $row['departamento'],
            'provincia' => $row['provincia'],
            'distrito' => $row['distrito'],
            'direccion' => $row['direccion'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        if (DB::getDriverName() !== 'pgsql') {
            foreach (array_chunk($records, 500) as $chunk) {
                DB::table('ruc_staging')->insert($chunk);
            }

            return;
        }

        $lines = array_map(fn (array $record): string => implode("\t", array_map($this->escape(...), $record)), $records);
        $pdo = DB::connection()->getPdo();
        if (! is_callable([$pdo, 'pgsqlCopyFromArray'])) {
            throw new RuntimeException('El driver PostgreSQL no expone COPY para la importación RUC.');
        }
        $ok = call_user_func([$pdo, 'pgsqlCopyFromArray'], 'ruc_staging', $lines, "\t", '\\N', implode(',', self::COLUMNS));
        if ($ok !== true) {
            throw new RuntimeException('PostgreSQL COPY no pudo cargar el lote RUC en staging.');
        }
    }

    private function escape(mixed $value): string
    {
        if ($value === null) {
            return '\\N';
        }

        return str_replace(['\\', "\t", "\r", "\n"], ['\\\\', '\\t', '\\r', '\\n'], (string) $value);
    }
}
