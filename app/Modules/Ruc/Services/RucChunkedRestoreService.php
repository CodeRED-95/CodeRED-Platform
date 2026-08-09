<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Support\RucBackupArchive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Restauración por lotes desde un .rucbackup, reanudable y sin exponer nunca
 * la tabla activa a un estado intermedio.
 *
 * REGLA CENTRAL: los datos JAMÁS se cargan sobre ruc_records. Se construye
 * una tabla nueva, ruc_records_next, y solo cuando el dataset completo está
 * verificado se hace el intercambio de nombres. Mientras dura la carga
 * —minutos u horas— ruc_records sigue sirviendo consultas con normalidad, y
 * si cualquier lote falla basta con no hacer el swap: la tabla activa no se
 * ha tocado en ningún momento.
 *
 *   validar manifest -> verificar checksums -> crear staging
 *     -> [COPY lote 1 -> checkpoint] -> [COPY lote 2 -> checkpoint] -> ...
 *     -> verificar total -> índices -> ANALYZE -> swap atómico
 *
 * SIN TRANSACCIÓN GIGANTE: cada lote es su propia unidad. Envolver 18M filas
 * en una sola transacción haría crecer el WAL sin control y un fallo al 95%
 * tiraría horas de trabajo. Como se carga en una tabla que nadie consulta,
 * la atomicidad se necesita solo en el swap final, que es instantáneo.
 */
class RucChunkedRestoreService
{
    public const ACTIVE_TABLE = 'ruc_records';

    public const STAGING_TABLE = 'ruc_records_next';

    public const OLD_TABLE = 'ruc_records_old';

    public function __construct(private readonly RucBackupProcessRunner $runner) {}

    /**
     * @param  null|callable(array<string, mixed>): void  $onProgress
     * @return array<string, mixed>
     */
    public function restore(
        RucBackup $backup,
        RucBackupOperation $operation,
        bool $resume = false,
        ?callable $onProgress = null,
    ): array {
        $path = $backup->absolutePath();
        $startedAt = microtime(true);

        // --- 1. Validación completa ANTES de crear nada -------------------
        $manifest = RucBackupArchive::readManifest($path);
        RucBackupArchive::assertManifestIsValid($manifest);
        $this->assertSufficientDiskSpace($manifest, $path);

        $totalBatches = (int) $manifest['total_batches'];
        $totalRecords = (int) $manifest['total_records'];

        $startFrom = 1;
        $recordsProcessed = 0;
        $bytesProcessed = 0;
        // Registros que ya venían cargados de un intento anterior: se
        // descuentan al calcular reg/s para que la velocidad refleje ESTA
        // ejecución y no salga inflada al reanudar.
        $recordsBeforeThisRun = 0;

        if ($resume) {
            $state = $this->prepareResume($operation, $manifest);
            $startFrom = $state['next_batch'];
            $recordsProcessed = $state['records_processed'];
            $bytesProcessed = $state['bytes_processed'];
            $recordsBeforeThisRun = $state['records_processed'];
        } else {
            // La verificación de checksums es cara (lee el archivo entero).
            // Se hace solo en el arranque limpio: al reanudar ya se validó y
            // el manifest no ha cambiado (se comprueba por chunks_sha256).
            RucBackupArchive::verifyChunks($path, $manifest);
            $this->createStagingTable();
        }

        $operation->update([
            'total_batches' => $totalBatches,
            'staging_table' => self::STAGING_TABLE,
        ]);

        // --- 2. Carga lote a lote ----------------------------------------
        $zip = RucBackupArchive::openForRead($path);

        try {
            for ($number = $startFrom; $number <= $totalBatches; $number++) {
                if ($this->isCancelRequested($operation)) {
                    return $this->finishCancelled($operation, $number, $recordsProcessed);
                }

                $chunk = RucBackupArchive::findChunk($manifest, $number);
                if ($chunk === null) {
                    throw new RuntimeException("El manifest no contiene el lote {$number}.");
                }

                $operation->update(['current_batch' => $number]);

                $this->copyChunkIntoStaging($zip, $chunk);

                $recordsProcessed += (int) $chunk['records'];
                $bytesProcessed += (int) $chunk['compressed_size'];

                $this->saveCheckpoint(
                    $operation,
                    $number,
                    $totalBatches,
                    $recordsProcessed,
                    $bytesProcessed,
                    $chunk,
                    (string) ($manifest['chunks_sha256'] ?? '')
                );

                if ($onProgress !== null) {
                    $elapsed = max(0.001, microtime(true) - $startedAt);
                    $done = $recordsProcessed - $recordsBeforeThisRun;
                    $onProgress([
                        'batch' => $number,
                        'total_batches' => $totalBatches,
                        'records' => $recordsProcessed,
                        'total_records' => $totalRecords,
                        'percent' => $totalRecords > 0 ? round($recordsProcessed / $totalRecords * 100, 2) : 0.0,
                        'records_per_second' => (int) round($done / $elapsed),
                        'bytes_per_second' => (int) round($bytesProcessed / $elapsed),
                        'elapsed' => $elapsed,
                        'eta_seconds' => $this->eta($recordsProcessed, $totalRecords, $elapsed),
                    ]);
                }
            }
        } finally {
            $zip->close();
        }

        // --- 3. Verificación del dataset completo ------------------------
        $stagedCount = (int) DB::table(self::STAGING_TABLE)->count();

        if ($stagedCount !== $totalRecords) {
            throw new RuntimeException(sprintf(
                'El staging tiene %d registros pero el backup declara %d. No se hace el swap; ruc_records sigue intacta.',
                $stagedCount,
                $totalRecords
            ));
        }

        // --- 4. Índices y estadísticas antes de exponer la tabla ---------
        $this->buildIndexes();
        $this->vacuumStaging();

        // --- 5. Swap atómico ---------------------------------------------
        $recordsBefore = $this->countActiveRecords();
        $this->swapTables();

        $duration = (int) round(microtime(true) - $startedAt);

        $operation->update([
            'status' => RucBackupOperation::STATUS_COMPLETED,
            'stage' => RucBackupOperation::STAGE_COMPLETED,
            'progress' => 100,
            'message' => 'Completado',
            'records_before' => $recordsBefore,
            'records_after' => $stagedCount,
            'completed_batches' => $totalBatches,
            'records_processed' => $recordsProcessed,
            'duration_seconds' => $duration,
            'finished_at' => now(),
        ]);

        Log::info('RUC chunked restore completed', [
            'operation_id' => $operation->id,
            'backup_id' => $backup->id,
            'records_before' => $recordsBefore,
            'records_after' => $stagedCount,
            'batches' => $totalBatches,
            'duration_seconds' => $duration,
        ]);

        return [
            'records_restored' => $stagedCount,
            'records_before' => $recordsBefore,
            'batches' => $totalBatches,
            'duration_seconds' => $duration,
            'old_table_kept' => (bool) config('ruc.backup.chunked.keep_old_table', true),
        ];
    }

    /**
     * Carga un chunk en la tabla de staging enteramente por tubería:
     *
     *     ZipArchive::getStream()  ->  zstd -d  ->  psql \copy FROM STDIN
     *
     * El chunk nunca se extrae a disco ni se materializa en memoria de PHP;
     * Symfony Process conecta el recurso del ZIP a la entrada estándar y el
     * sistema operativo hace el resto.
     *
     * @param  array<string, mixed>  $chunk
     */
    private function copyChunkIntoStaging(\ZipArchive $zip, array $chunk): void
    {
        $stream = RucBackupArchive::chunkStream($zip, (string) $chunk['filename']);

        try {
            $columns = implode(', ', RucBackupArchive::COLUMNS);
            $copy = sprintf(
                '\copy %s (%s) FROM STDIN WITH (FORMAT csv)',
                self::STAGING_TABLE,
                $columns
            );

            $shell = sprintf(
                'set -o pipefail; zstd -d -q -c | psql %s --no-psqlrc --set=ON_ERROR_STOP=1 -c %s',
                $this->psqlConnectionArgs(),
                escapeshellarg($copy)
            );

            $process = Process::fromShellCommandline($shell);
            $process->setTimeout(null);
            $process->setEnv(['PGPASSWORD' => (string) $this->dbConfig('password')]);
            $process->setInput($stream);
            $this->runner->run($process);

            if (! $process->isSuccessful()) {
                throw new RuntimeException(sprintf(
                    'Falló la carga del lote %d (%s): %s. ruc_records NO se modificó.',
                    $chunk['number'],
                    $chunk['filename'],
                    trim($process->getErrorOutput())
                ));
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Staging sin índices: cargar 18M filas contra 9 índices multiplica el
     * tiempo de COPY. Se crean después, de golpe, que es mucho más rápido.
     */
    private function createStagingTable(): void
    {
        DB::statement('DROP TABLE IF EXISTS '.self::STAGING_TABLE);
        DB::statement(sprintf(
            'CREATE TABLE %s (LIKE %s INCLUDING DEFAULTS)',
            self::STAGING_TABLE,
            self::ACTIVE_TABLE
        ));
    }

    private function buildIndexes(): void
    {
        $t = self::STAGING_TABLE;

        // Mismos índices que ruc_records (ver la migración
        // 2026_08_09_000004_optimize_ruc_records_list_indexes). Los nombres
        // llevan el sufijo _next y se renombran en el swap, porque PostgreSQL
        // no admite dos índices con el mismo nombre en el esquema.
        //
        // Los filtros del listado usan índices COMPUESTOS (columna, id): la
        // consulta real es `WHERE columna = ? ORDER BY id LIMIT 51`, y un
        // índice de una sola columna obliga a ordenar después, así que el
        // planificador acaba recorriendo la clave primaria. Solo se indexan
        // las columnas con cardinalidad suficiente (provincia, distrito,
        // ubigeo); estado, condicion y departamento tienen tan pocos valores
        // distintos que el recorrido por clave primaria ya resuelve en ~1 ms.
        $statements = [
            "ALTER TABLE {$t} ADD CONSTRAINT {$t}_pkey PRIMARY KEY (id)",
            "ALTER TABLE {$t} ADD CONSTRAINT {$t}_ruc_unique UNIQUE (ruc)",
            "CREATE INDEX {$t}_provincia_id_index ON {$t} (provincia, id)",
            "CREATE INDEX {$t}_distrito_id_index ON {$t} (distrito, id)",
            "CREATE INDEX {$t}_ubigeo_id_index ON {$t} (ubigeo, id)",
            "CREATE INDEX {$t}_razon_social_trgm_index ON {$t} USING gin (razon_social gin_trgm_ops)",
        ];

        foreach ($statements as $sql) {
            DB::statement($sql);
        }
    }

    /**
     * VACUUM ANALYZE sobre el staging antes de exponerlo.
     *
     * ANALYZE por sí solo NO basta. Un índice GIN construido durante una carga
     * masiva acumula entradas en su "pending list", y mientras no se vacía
     * cada búsqueda por razón social tiene que recorrerla de forma lineal.
     * Medido sobre 18M filas: la misma búsqueda tardaba 6 996 ms antes del
     * VACUUM y 1 148 ms después. Solo VACUUM vacía esa lista.
     *
     * Se ejecuta sobre la tabla de staging, que todavía no atiende consultas,
     * así que no bloquea a nadie.
     */
    private function vacuumStaging(): void
    {
        // VACUUM no puede ejecutarse dentro de una transacción; el statement
        // va directo por la conexión, fuera de cualquier bloque transaccional.
        DB::statement('VACUUM ANALYZE '.self::STAGING_TABLE);
    }

    /**
     * Intercambio de nombres dentro de una transacción. Es la única parte
     * que bloquea ruc_records y dura milisegundos: ALTER TABLE ... RENAME
     * solo actualiza el catálogo, no mueve datos.
     */
    private function swapTables(): void
    {
        $keepOld = (bool) config('ruc.backup.chunked.keep_old_table', true);

        // El nombre real de la secuencia hay que averiguarlo ANTES del swap:
        // pg_get_serial_sequence se apoya en la dependencia de pertenencia,
        // que en este momento todavía apunta a la tabla activa.
        $sequence = $this->sequenceOf(self::ACTIVE_TABLE, 'id');

        DB::transaction(function () use ($sequence): void {
            DB::statement('DROP TABLE IF EXISTS '.self::OLD_TABLE.' CASCADE');

            // Los índices NO se renombran con su tabla: si no se apartan
            // primero, al renombrar los del staging a su nombre canónico
            // chocarían con los de la tabla saliente, que sigue ocupándolos.
            // (Ocurrió: "relation ruc_records_estado_index already exists".)
            $this->renameIndexes(self::ACTIVE_TABLE, static fn (string $n): string => 'zz_old_'.$n);

            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', self::ACTIVE_TABLE, self::OLD_TABLE));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', self::STAGING_TABLE, self::ACTIVE_TABLE));

            // Ahora los índices del staging (ruc_records_next_*) pasan al
            // nombre canónico, de modo que la tabla activa quede idéntica a
            // como la dejan las migraciones y un futuro swap no colisione.
            $this->renameIndexes(
                self::ACTIVE_TABLE,
                static fn (string $n): string => str_starts_with($n, self::STAGING_TABLE.'_')
                    ? self::ACTIVE_TABLE.'_'.substr($n, strlen(self::STAGING_TABLE) + 1)
                    : $n
            );

            // CRÍTICO. La secuencia de `id` sigue perteneciendo a la tabla
            // que acaba de salir: `CREATE TABLE (LIKE ... INCLUDING DEFAULTS)`
            // copia el DEFAULT nextval(...) pero no la pertenencia. Si se
            // dejara así, un `DROP TABLE ruc_records_old CASCADE` —que hace
            // el propio swap siguiente, o keep_old_table=false— arrastraría
            // la secuencia y TODOS los INSERT sobre la tabla activa fallarían
            // con "relation ruc_records_id_seq does not exist".
            //
            // Se reasigna la pertenencia y se coloca el contador por encima
            // del mayor id restaurado, para que el primer INSERT posterior no
            // choque con la clave primaria.
            if ($sequence !== null) {
                DB::statement(sprintf('ALTER SEQUENCE %s OWNED BY %s.id', $sequence, self::ACTIVE_TABLE));
                DB::statement(sprintf(
                    'SELECT setval(%s, GREATEST(COALESCE((SELECT MAX(id) FROM %s), 0), 1), true)',
                    "'".$sequence."'",
                    self::ACTIVE_TABLE
                ));
            }
        });

        if (! $keepOld) {
            DB::statement('DROP TABLE IF EXISTS '.self::OLD_TABLE.' CASCADE');
        }
    }

    /**
     * Renombra TODOS los índices de una tabla aplicando $rename a cada
     * nombre. Se consulta pg_indexes en vez de mantener una lista fija: así
     * añadir un índice a ruc_records en una futura migración no rompe el
     * swap silenciosamente.
     *
     * @param  callable(string): string  $rename
     */
    private function renameIndexes(string $table, callable $rename): void
    {
        $indexes = DB::select(
            'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?',
            [$table]
        );

        foreach ($indexes as $index) {
            $from = (string) $index->indexname;
            $to = $rename($from);

            if ($to === $from) {
                continue;
            }

            // Límite de identificador de PostgreSQL: 63 bytes. Truncar
            // silenciosamente produciría colisiones difíciles de depurar.
            if (strlen($to) > 63) {
                throw new RuntimeException("El nombre de índice \"{$to}\" excede los 63 caracteres de PostgreSQL.");
            }

            DB::statement(sprintf('ALTER INDEX IF EXISTS %s RENAME TO %s', $from, $to));
        }
    }

    /**
     * Devuelve ruc_records_old al puesto activo. Solo tiene sentido mientras
     * keep_old_table siga conservándola.
     */
    public function rollbackSwap(): void
    {
        if (! $this->tableExists(self::OLD_TABLE)) {
            throw new RuntimeException('No existe '.self::OLD_TABLE.': no hay nada que revertir.');
        }

        $sequence = $this->sequenceOf(self::ACTIVE_TABLE, 'id');

        DB::transaction(function () use ($sequence): void {
            DB::statement('DROP TABLE IF EXISTS '.self::STAGING_TABLE.' CASCADE');

            // Simétrico al swap: apartar los índices de la tabla que sale
            // antes de devolver a los de la que entra su nombre canónico.
            $this->renameIndexes(self::ACTIVE_TABLE, static fn (string $n): string => 'zz_new_'.$n);

            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', self::ACTIVE_TABLE, self::STAGING_TABLE));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', self::OLD_TABLE, self::ACTIVE_TABLE));

            // Los índices de la tabla restaurada volvieron con el prefijo
            // zz_old_ que les puso el swap: se les quita.
            $this->renameIndexes(
                self::ACTIVE_TABLE,
                static fn (string $n): string => str_starts_with($n, 'zz_old_') ? substr($n, 7) : $n
            );

            // Y los de la tabla desplazada pasan a nombres de staging, para
            // que un nuevo intento de restore pueda volver a usarlos.
            $this->renameIndexes(
                self::STAGING_TABLE,
                static fn (string $n): string => str_starts_with($n, 'zz_new_'.self::ACTIVE_TABLE.'_')
                    ? self::STAGING_TABLE.'_'.substr($n, strlen('zz_new_'.self::ACTIVE_TABLE.'_'))
                    : $n
            );

            // Misma reasignación que en el swap, en sentido contrario.
            if ($sequence !== null) {
                DB::statement(sprintf('ALTER SEQUENCE %s OWNED BY %s.id', $sequence, self::ACTIVE_TABLE));
                DB::statement(sprintf(
                    'SELECT setval(%s, GREATEST(COALESCE((SELECT MAX(id) FROM %s), 0), 1), true)',
                    "'".$sequence."'",
                    self::ACTIVE_TABLE
                ));
            }
        });

        Log::warning('RUC restore rolled back: ruc_records_old promoted back to ruc_records');
    }

    /**
     * Comprueba que el staging encontrado pertenece a ESTA operación y que
     * el número de filas cuadra con los lotes ya confirmados. Nunca se salta
     * un lote sin verificarlo.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{next_batch: int, records_processed: int, bytes_processed: int}
     */
    private function prepareResume(RucBackupOperation $operation, array $manifest): array
    {
        if (! $this->tableExists(self::STAGING_TABLE)) {
            throw new RuntimeException(
                'No existe '.self::STAGING_TABLE.': no hay ninguna restauración que reanudar. '.
                'Ejecuta la restauración sin --resume.'
            );
        }

        $checkpoint = $operation->checkpoint;
        if (! is_array($checkpoint) || ! isset($checkpoint['batch'])) {
            throw new RuntimeException('La operación no tiene checkpoint: no se puede reanudar de forma segura.');
        }

        if (($checkpoint['chunks_sha256'] ?? null) !== ($manifest['chunks_sha256'] ?? null)) {
            throw new RuntimeException(
                'El archivo de backup no coincide con el de la operación interrumpida. '.
                'Reanudar mezclaría datos de dos backups distintos.'
            );
        }

        $completed = (int) $checkpoint['batch'];
        $expectedRows = (int) $checkpoint['records_processed'];
        $actualRows = (int) DB::table(self::STAGING_TABLE)->count();

        if ($actualRows !== $expectedRows) {
            throw new RuntimeException(sprintf(
                '%s tiene %d filas pero el checkpoint del lote %d declara %d. '.
                'El estado es inconsistente; reinicia la restauración sin --resume.',
                self::STAGING_TABLE,
                $actualRows,
                $completed,
                $expectedRows
            ));
        }

        Log::info('RUC chunked restore resuming', [
            'operation_id' => $operation->id,
            'from_batch' => $completed + 1,
            'records_already_loaded' => $actualRows,
        ]);

        return [
            'next_batch' => $completed + 1,
            'records_processed' => $expectedRows,
            'bytes_processed' => (int) ($checkpoint['bytes_processed'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $chunk
     */
    private function saveCheckpoint(
        RucBackupOperation $operation,
        int $batch,
        int $totalBatches,
        int $recordsProcessed,
        int $bytesProcessed,
        array $chunk,
        string $chunksSha256,
    ): void {
        $operation->update([
            'status' => RucBackupOperation::STATUS_RUNNING,
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'completed_batches' => $batch,
            'current_batch' => $batch,
            'records_processed' => $recordsProcessed,
            'bytes_processed' => $bytesProcessed,
            'progress' => $totalBatches > 0 ? (int) floor($batch / $totalBatches * 90) : 0,
            'message' => sprintf('Lote %d/%d · %s registros', $batch, $totalBatches, number_format($recordsProcessed)),
            'checkpoint' => [
                'batch' => $batch,
                'chunk_sha256' => $chunk['sha256'],
                // Identifica el backup concreto: reanudar con OTRO archivo
                // mezclaría dos datasets distintos en la misma tabla.
                'chunks_sha256' => $chunksSha256,
                'records_processed' => $recordsProcessed,
                'bytes_processed' => $bytesProcessed,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function isCancelRequested(RucBackupOperation $operation): bool
    {
        return $operation->fresh()?->cancel_requested_at !== null;
    }

    /**
     * @return array<string, mixed>
     */
    private function finishCancelled(RucBackupOperation $operation, int $batch, int $recordsProcessed): array
    {
        $operation->update([
            'status' => RucBackupOperation::STATUS_CANCELLED,
            'message' => sprintf('Cancelado antes del lote %d. ruc_records no fue modificada.', $batch),
            'finished_at' => now(),
        ]);

        Log::warning('RUC chunked restore cancelled', [
            'operation_id' => $operation->id,
            'stopped_before_batch' => $batch,
            'records_loaded' => $recordsProcessed,
        ]);

        return [
            'cancelled' => true,
            'stopped_before_batch' => $batch,
            'records_loaded' => $recordsProcessed,
            'staging_table_kept' => self::STAGING_TABLE,
        ];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertSufficientDiskSpace(array $manifest, string $archivePath): void
    {
        $uncompressed = array_sum(array_map(
            static fn (array $c): int => (int) ($c['uncompressed_size'] ?? 0),
            $manifest['chunks']
        ));

        // Si el manifest no trae tamaños descomprimidos (backup antiguo del
        // formato), se estima con un ratio conservador sobre el comprimido.
        if ($uncompressed === 0) {
            $uncompressed = (int) (filesize($archivePath) * 5);
        }

        // La tabla ocupa aproximadamente el CSV, y los índices otro tanto.
        $required = (int) ($uncompressed * 2.2);
        $free = @disk_free_space(DIRECTORY_SEPARATOR);

        if ($free === false) {
            return;
        }

        if ($free < $required) {
            throw new RuntimeException(sprintf(
                'Espacio insuficiente para la restauración: se estiman %s necesarios y hay %s libres. '.
                'Cancelada antes de tocar la base de datos.',
                $this->formatBytes($required),
                $this->formatBytes((int) $free)
            ));
        }
    }

    private function eta(int $done, int $total, float $elapsed): ?int
    {
        if ($done <= 0 || $total <= 0 || $done >= $total) {
            return null;
        }

        return (int) round(($total - $done) / ($done / $elapsed));
    }

    /**
     * Secuencia que alimenta el DEFAULT de una columna, o null si no hay.
     * Se resuelve por catálogo en vez de componer "<tabla>_<col>_seq" a mano:
     * el nombre real puede diferir si la tabla se creó de otra forma.
     */
    private function sequenceOf(string $table, string $column): ?string
    {
        $row = DB::selectOne('SELECT pg_get_serial_sequence(?, ?) AS seq', [$table, $column]);

        return $row?->seq ?: null;
    }

    public function tableExists(string $table): bool
    {
        return DB::selectOne('SELECT to_regclass(?) IS NOT NULL AS present', ['public.'.$table])->present ?? false;
    }

    private function countActiveRecords(): int
    {
        return (int) DB::table(self::ACTIVE_TABLE)->count();
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;
        for (; $value > 1024 && $i < count($units) - 1; $i++) {
            $value /= 1024;
        }

        return round($value, 2).' '.$units[$i];
    }

    private function psqlConnectionArgs(): string
    {
        return sprintf(
            '--host=%s --port=%s --username=%s --dbname=%s',
            escapeshellarg((string) $this->dbConfig('host')),
            escapeshellarg((string) $this->dbConfig('port', '5432')),
            escapeshellarg((string) $this->dbConfig('username')),
            escapeshellarg((string) $this->dbConfig('database'))
        );
    }

    private function dbConfig(string $key, ?string $default = null): ?string
    {
        return config("database.connections.pgsql.{$key}", $default);
    }
}
