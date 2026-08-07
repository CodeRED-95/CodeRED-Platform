<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Data\BatchInsertResult;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Support\Facades\DB;

class RucBatchInserter
{
    /**
     * Inserta un lote de registros usando UPSERT
     */
    public function insert(
        RucImport $import,
        array $records,
        ?string $strategy = null
    ): BatchInsertResult {
        if (empty($records)) {
            return new BatchInsertResult;
        }

        $strategy = $strategy ?? $import->merge_strategy ?? 'insert';

        if (DB::getDriverName() !== 'pgsql') {
            return $this->insertMysql($import, $records, $strategy);
        }

        return $this->insertPostgres($import, $records, $strategy);
    }

    /**
     * Inserción en PostgreSQL usando ON CONFLICT
     */
    private function insertPostgres(RucImport $import, array $records, string $strategy): BatchInsertResult
    {
        $result = new BatchInsertResult;

        if (empty($records)) {
            return $result;
        }

        $now = now()->toDateTimeString();

        // Preparar valores para insert
        $values = [];
        $params = [];
        $columnCount = 9; // ruc, razon_social, estado, condicion, ubigeo, direccion, ruc_import_id, created_at, updated_at

        // OJO: placeholders "?" (estilo PDO), no "$1,$2,..." (sintaxis nativa
        // de Postgres). DB::statement()/PDO no vincula valores a "$N": la
        // consulta se ejecutaba "bien" pero insertando NULL en todas las
        // columnas, violando el NOT NULL de "ruc" y abortando la transacción
        // en cada lote (por eso el fallback fila-por-fila también fallaba
        // silenciosamente: la transacción ya estaba abortada).
        $rowPlaceholders = '('.implode(',', array_fill(0, $columnCount, '?')).')';

        foreach ($records as $record) {
            $values[] = $rowPlaceholders;

            $params[] = $record['ruc'];
            $params[] = $record['razon_social'];
            $params[] = $record['estado'];
            $params[] = $record['condicion'];
            $params[] = $record['ubigeo'];
            $params[] = $record['direccion'];
            $params[] = $import->id;
            $params[] = $now;
            $params[] = $now;
        }

        // ruc_import_id no se sobrescribe en conflicto: debe seguir apuntando
        // a la importación que originalmente creó el registro, para que un
        // rollback posterior solo afecte lo que esa importación insertó.
        $columnList = 'ruc,razon_social,estado,condicion,ubigeo,direccion,ruc_import_id,created_at,updated_at';

        // Construir SQL según estrategia
        $sql = "INSERT INTO ruc_records ({$columnList}) VALUES ".implode(',', $values);

        match ($strategy) {
            'insert' => $sql .= ' ON CONFLICT (ruc) DO NOTHING',
            'insert_update' => $sql .= ' ON CONFLICT (ruc) DO UPDATE SET razon_social=EXCLUDED.razon_social,estado=EXCLUDED.estado,condicion=EXCLUDED.condicion,ubigeo=EXCLUDED.ubigeo,direccion=EXCLUDED.direccion,updated_at=NOW()',
            'replace' => $sql .= ' ON CONFLICT (ruc) DO UPDATE SET razon_social=EXCLUDED.razon_social,estado=EXCLUDED.estado,condicion=EXCLUDED.condicion,ubigeo=EXCLUDED.ubigeo,direccion=EXCLUDED.direccion,updated_at=NOW()',
            default => throw new \InvalidArgumentException("Estrategia desconocida: {$strategy}"),
        };

        try {
            $affected = DB::statement($sql, $params);
            $result->inserted = count($records); // PostgreSQL UPSERT retorna número de filas insertadas
        } catch (\Exception $e) {
            // Fallback a insert uno por uno si falla
            $result->inserted = 0;
            foreach ($records as $record) {
                try {
                    $inserted = DB::table('ruc_records')->insertOrIgnore([
                        'ruc' => $record['ruc'],
                        'razon_social' => $record['razon_social'],
                        'estado' => $record['estado'],
                        'condicion' => $record['condicion'],
                        'ubigeo' => $record['ubigeo'],
                        'direccion' => $record['direccion'],
                        'ruc_import_id' => $import->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $result->inserted += $inserted;
                } catch (\Exception) {
                    // Saltar registro si falla
                }
            }
        }

        $result->skipped = count($records) - $result->inserted;

        return $result;
    }

    /**
     * Inserción en MySQL usando INSERT IGNORE
     */
    private function insertMysql(RucImport $import, array $records, string $strategy): BatchInsertResult
    {
        $result = new BatchInsertResult;

        if (empty($records)) {
            return $result;
        }

        $now = now()->toDateTimeString();
        $toInsert = [];

        foreach ($records as $record) {
            $toInsert[] = [
                'ruc' => $record['ruc'],
                'razon_social' => $record['razon_social'],
                'estado' => $record['estado'],
                'condicion' => $record['condicion'],
                'ubigeo' => $record['ubigeo'],
                'direccion' => $record['direccion'],
                'ruc_import_id' => $import->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Procesar en chunks de 500
        foreach (array_chunk($toInsert, 500) as $chunk) {
            if ($strategy === 'insert') {
                $result->inserted += DB::table('ruc_records')->insertOrIgnore($chunk);
            } else {
                // MySQL: usar INSERT ... ON DUPLICATE KEY UPDATE
                try {
                    DB::table('ruc_records')->upsert($chunk, 'ruc', [
                        'razon_social',
                        'estado',
                        'condicion',
                        'ubigeo',
                        'direccion',
                        'updated_at',
                    ]);
                    $result->inserted += count($chunk);
                } catch (\Exception) {
                    // Fallback a insert uno por uno
                    foreach ($chunk as $record) {
                        try {
                            DB::table('ruc_records')->insertOrIgnore($record);
                            $result->inserted++;
                        } catch (\Exception) {
                            // Saltar
                        }
                    }
                }
            }
        }

        $result->skipped = count($records) - $result->inserted;

        return $result;
    }
}
