<?php

namespace RucTool\Services;

use RucTool\Database\Connection;

/**
 * Importa el padrón reducido de RUC (SUNAT) directamente a PostgreSQL usando
 * el mismo mecanismo de alto rendimiento que producción: COPY nativo a una
 * tabla staging UNLOGGED, seguido de un merge deduplicado a ruc_records
 * (equivalente a RucCopyLoader + RucMergeService en CodeRED-Platform).
 */
class ImportService
{
    private Connection $connection;

    private PadronParser $parser;

    private UbigeoService $ubigeoService;

    private array $config;

    private const STAGING_COLUMNS = [
        'import_id', 'row_number', 'ruc', 'razon_social', 'estado', 'condicion',
        'ubigeo', 'departamento', 'provincia', 'distrito', 'direccion',
        'tipo_via', 'nombre_via', 'codigo_zona', 'tipo_zona', 'numero',
        'interior', 'lote', 'departamento_direccion', 'manzana', 'kilometro',
        'created_at', 'updated_at',
    ];

    public function __construct(
        Connection $connection,
        PadronParser $parser,
        UbigeoService $ubigeoService,
        array $config = []
    ) {
        $this->connection = $connection;
        $this->parser = $parser;
        $this->ubigeoService = $ubigeoService;
        $this->config = $config;
    }

    public function importFile(string $filepath, array $options = [], ?callable $progressCallback = null): array
    {
        if (! file_exists($filepath)) {
            throw new \Exception("File not found: $filepath");
        }

        $delimiter = $options['delimiter'] ?? '|';
        $encoding = $options['encoding'] ?? 'ISO-8859-1';
        $strategy = $options['strategy'] ?? 'insert';
        $batchSize = (int) ($options['batch_size'] ?? $this->config['copy_batch_size'] ?? 50000);

        $importId = $this->createImportRecord(basename($filepath));
        $startTime = microtime(true);

        $totalLines = $this->countLines($filepath);

        $handle = fopen($filepath, 'rb');
        if ($handle === false) {
            throw new \Exception("Could not open file: $filepath");
        }

        $lineNumber = 0;
        $validLines = 0;
        $errorLines = 0;
        $batchRows = [];
        $batchErrors = [];

        try {
            while (($rawLine = fgets($handle)) !== false) {
                $lineNumber++;

                $line = rtrim($rawLine, "\r\n");
                if ($line === '') {
                    continue;
                }

                $result = $this->parser->parse($line, $delimiter, $encoding);

                if (isset($result['header'])) {
                    continue;
                }

                if (isset($result['error'])) {
                    $errorLines++;
                    $batchErrors[] = [$lineNumber, $result['error'], mb_substr($line, 0, 300)];
                } else {
                    $data = $result['data'];
                    $location = $this->ubigeoService->resolve($data['ubigeo']);
                    $data['departamento'] = $location['departamento'];
                    $data['provincia'] = $location['provincia'];
                    $data['distrito'] = $location['distrito'];

                    $validLines++;
                    $batchRows[] = ['row_number' => $lineNumber] + $data;
                }

                if (count($batchRows) + count($batchErrors) >= $batchSize) {
                    $this->flushBatch($importId, $batchRows, $batchErrors);
                    $batchRows = [];
                    $batchErrors = [];

                    if ($progressCallback) {
                        $progressCallback([
                            'total' => $totalLines,
                            'processed' => $lineNumber,
                            'valid' => $validLines,
                            'errors' => $errorLines,
                        ]);
                    }
                }
            }

            if (! empty($batchRows) || ! empty($batchErrors)) {
                $this->flushBatch($importId, $batchRows, $batchErrors);
                if ($progressCallback) {
                    $progressCallback([
                        'total' => $totalLines,
                        'processed' => $lineNumber,
                        'valid' => $validLines,
                        'errors' => $errorLines,
                    ]);
                }
            }
        } finally {
            fclose($handle);
        }

        // Merge: staging -> ruc_records (deduplicado, igual a RucMergeService)
        if ($progressCallback) {
            $progressCallback([
                'phase' => 'merge',
                'total' => $totalLines,
                'processed' => $lineNumber,
                'valid' => $validLines,
                'errors' => $errorLines,
            ]);
        }
        $mergeResult = $this->merge($importId, $strategy);

        // Limpiar staging de este import
        $this->connection->query('DELETE FROM ruc_staging WHERE import_id = ?', [$importId]);

        $duration = max(1, (int) (microtime(true) - $startTime));

        $this->connection->update('ruc_tool_imports', [
            'total_lines' => $lineNumber,
            'valid_lines' => $validLines,
            'error_lines' => $errorLines,
            'duplicate_lines' => $mergeResult['duplicates'],
            'inserted_records' => $mergeResult['inserted'],
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'duration_seconds' => $duration,
            'lines_per_second' => round($lineNumber / $duration, 2),
        ], ['id' => $importId]);

        return [
            'import_id' => $importId,
            'total' => $lineNumber,
            'valid' => $validLines,
            'errors' => $errorLines,
            'duplicates' => $mergeResult['duplicates'],
            'inserted' => $mergeResult['inserted'],
            'duration_seconds' => $duration,
            'lines_per_second' => round($lineNumber / $duration, 2),
        ];
    }

    private function flushBatch(int $importId, array $rows, array $errors): void
    {
        if (! empty($rows)) {
            $this->copyToStaging($importId, $rows);
        }

        if (! empty($errors)) {
            $this->insertErrors($importId, $errors);
        }
    }

    private function copyToStaging(int $importId, array $rows): void
    {
        $now = date('Y-m-d H:i:s');

        $lines = array_map(function (array $row) use ($importId, $now): string {
            $ordered = [
                $importId,
                $row['row_number'],
                $row['ruc'],
                $row['razon_social'],
                $row['estado'],
                $row['condicion'],
                $row['ubigeo'],
                $row['departamento'],
                $row['provincia'],
                $row['distrito'],
                $row['direccion'],
                $row['tipo_via'],
                $row['nombre_via'],
                $row['codigo_zona'],
                $row['tipo_zona'],
                $row['numero'],
                $row['interior'],
                $row['lote'],
                $row['departamento_direccion'],
                $row['manzana'],
                $row['kilometro'],
                $now,
                $now,
            ];

            return implode("\t", array_map($this->escapeForCopy(...), $ordered));
        }, $rows);

        $pdo = $this->connection->getPdo();
        if (! is_callable([$pdo, 'pgsqlCopyFromArray'])) {
            throw new \RuntimeException('El driver PostgreSQL no expone COPY (pgsqlCopyFromArray).');
        }

        // IMPORTANTE: pasar explícitamente el 4º parámetro ($nullAs) rompe la
        // detección de NULL en pdo_pgsql (produce el string literal "N" en vez
        // de NULL), incluso con el mismo valor por defecto. Se debe invocar con
        // solo 3 argumentos y confiar en el orden físico de columnas de
        // ruc_staging (ver self::STAGING_COLUMNS), que coincide con $ordered arriba.
        $ok = call_user_func([$pdo, 'pgsqlCopyFromArray'], 'ruc_staging', $lines, "\t");

        if ($ok !== true) {
            throw new \RuntimeException('PostgreSQL COPY no pudo cargar el lote en staging.');
        }
    }

    private function escapeForCopy(mixed $value): string
    {
        if ($value === null) {
            return '\\N';
        }

        return str_replace(['\\', "\t", "\r", "\n"], ['\\\\', '\\t', '\\r', '\\n'], (string) $value);
    }

    private function insertErrors(int $importId, array $errors): void
    {
        $placeholders = [];
        $params = [];

        foreach ($errors as [$lineNumber, $reason, $preview]) {
            $placeholders[] = '(?, ?, ?, ?)';
            array_push($params, $importId, $lineNumber, $reason, $preview);
        }

        $sql = 'INSERT INTO ruc_tool_import_errors (import_id, line_number, reason, line_preview) VALUES '
            .implode(',', $placeholders);

        $this->connection->query($sql, $params);
    }

    /**
     * Merge staging -> ruc_records deduplicando por RUC (ROW_NUMBER + ON CONFLICT),
     * idéntico a App\Modules\Ruc\Services\RucMergeService::merge().
     */
    /**
     * Índices secundarios de ruc_records (todo excepto la PK y el UNIQUE de
     * ruc, que ON CONFLICT necesita). Se dropean antes del merge masivo y se
     * reconstruyen una sola vez al final — mantenerlos fila por fila durante
     * un INSERT de millones de filas es dramáticamente más lento, sobre todo
     * el índice GIN trigram, pero también los btree de baja cardinalidad
     * (estado/condicion) por contención en sus pocas páginas hoja.
     */
    private const SECONDARY_INDEXES = [
        'ruc_records_razon_social_trgm_index' => 'CREATE INDEX IF NOT EXISTS ruc_records_razon_social_trgm_index ON ruc_records USING gin (razon_social gin_trgm_ops)',
        'ruc_records_estado_index' => 'CREATE INDEX IF NOT EXISTS ruc_records_estado_index ON ruc_records(estado)',
        'ruc_records_condicion_index' => 'CREATE INDEX IF NOT EXISTS ruc_records_condicion_index ON ruc_records(condicion)',
        'ruc_records_ubigeo_index' => 'CREATE INDEX IF NOT EXISTS ruc_records_ubigeo_index ON ruc_records(ubigeo)',
        'ruc_records_departamento_index' => 'CREATE INDEX IF NOT EXISTS ruc_records_departamento_index ON ruc_records(departamento)',
        'ruc_records_provincia_index' => 'CREATE INDEX IF NOT EXISTS ruc_records_provincia_index ON ruc_records(provincia)',
        'ruc_records_distrito_index' => 'CREATE INDEX IF NOT EXISTS ruc_records_distrito_index ON ruc_records(distrito)',
    ];

    private function merge(int $importId, string $strategy): array
    {
        // work_mem por defecto (256MB) hace que el planificador prefiera un
        // Index Scan para el ORDER BY del window function, lo que degenera en
        // I/O aleatorio por fila sobre millones de filas (extremadamente lento).
        // Con más work_mem, un Sort explícito (secuencial) sale más barato y
        // el planificador lo prefiere.
        $this->connection->exec("SET work_mem = '2GB'");

        foreach (array_keys(self::SECONDARY_INDEXES) as $indexName) {
            $this->connection->exec('DROP INDEX IF EXISTS '.$indexName);
        }

        try {
            $result = $this->doMerge($importId, $strategy);
        } finally {
            foreach (self::SECONDARY_INDEXES as $createSql) {
                $this->connection->exec($createSql);
            }
        }

        return $result;
    }

    private function doMerge(int $importId, string $strategy): array
    {
        $staged = $this->connection->query(
            'SELECT COUNT(*) AS c FROM ruc_staging WHERE import_id = ?',
            [$importId]
        )->fetch()['c'];

        $distinct = $this->connection->query(
            'SELECT COUNT(DISTINCT ruc) AS c FROM ruc_staging WHERE import_id = ?',
            [$importId]
        )->fetch()['c'];

        $conflictClause = $strategy === 'insert'
            ? 'ON CONFLICT (ruc) DO NOTHING'
            : 'ON CONFLICT (ruc) DO UPDATE SET
                razon_social = EXCLUDED.razon_social,
                estado = EXCLUDED.estado,
                condicion = EXCLUDED.condicion,
                ubigeo = EXCLUDED.ubigeo,
                departamento = EXCLUDED.departamento,
                provincia = EXCLUDED.provincia,
                distrito = EXCLUDED.distrito,
                direccion = EXCLUDED.direccion,
                tipo_via = EXCLUDED.tipo_via,
                nombre_via = EXCLUDED.nombre_via,
                codigo_zona = EXCLUDED.codigo_zona,
                tipo_zona = EXCLUDED.tipo_zona,
                numero = EXCLUDED.numero,
                interior = EXCLUDED.interior,
                lote = EXCLUDED.lote,
                departamento_direccion = EXCLUDED.departamento_direccion,
                manzana = EXCLUDED.manzana,
                kilometro = EXCLUDED.kilometro,
                updated_at = EXCLUDED.updated_at';

        $sql = "
            WITH ranked AS (
                SELECT *, row_number() OVER (PARTITION BY ruc ORDER BY row_number) AS duplicate_rank
                FROM ruc_staging WHERE import_id = ?
            ), merged AS (
                INSERT INTO ruc_records (
                    ruc, razon_social, estado, condicion, ubigeo, departamento, provincia, distrito, direccion,
                    tipo_via, nombre_via, codigo_zona, tipo_zona, numero, interior, lote,
                    departamento_direccion, manzana, kilometro, created_at, updated_at
                )
                SELECT
                    ruc, razon_social, estado, condicion, ubigeo, departamento, provincia, distrito, direccion,
                    tipo_via, nombre_via, codigo_zona, tipo_zona, numero, interior, lote,
                    departamento_direccion, manzana, kilometro, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                FROM ranked WHERE duplicate_rank = 1
                $conflictClause
                RETURNING ruc
            ) SELECT count(*)::bigint AS affected FROM merged
        ";

        $result = $this->connection->query($sql, [$importId])->fetch();

        return [
            'inserted' => (int) ($result['affected'] ?? 0),
            'duplicates' => (int) $staged - (int) $distinct,
        ];
    }

    private function countLines(string $filepath): int
    {
        $output = [];
        $returnVar = 0;
        @exec('wc -l '.escapeshellarg($filepath), $output, $returnVar);

        if ($returnVar === 0 && ! empty($output[0])) {
            $parts = preg_split('/\s+/', trim($output[0]));
            if (isset($parts[0]) && is_numeric($parts[0])) {
                return (int) $parts[0];
            }
        }

        $count = 0;
        $handle = fopen($filepath, 'rb');
        while (! feof($handle)) {
            $count += substr_count(fread($handle, 1024 * 1024), "\n");
        }
        fclose($handle);

        return $count;
    }

    private function createImportRecord(string $filename): int
    {
        return $this->connection->insert('ruc_tool_imports', [
            'filename' => $filename,
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
