<?php

declare(strict_types=1);

namespace Tests\Feature\Ruc;

use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Services\RucBackupProcessRunner;
use App\Modules\Ruc\Services\RucChunkedBackupService;
use App\Modules\Ruc\Services\RucChunkedRestoreService;
use App\Modules\Ruc\Support\RucBackupArchive;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

/**
 * Formato .rucbackup: generación troceada, validación, restauración por
 * lotes, reanudación, cancelación y rollback.
 *
 * Usa DatabaseTruncation y NO RefreshDatabase a propósito: backup y restore
 * se apoyan en psql, que abre su PROPIA conexión a PostgreSQL. Dentro de la
 * transacción sin confirmar de RefreshDatabase, ese proceso externo no vería
 * ninguna de las filas creadas por el test y todos los backups saldrían
 * vacíos.
 */
class RucChunkedBackupTest extends TestCase
{
    use DatabaseTruncation;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        // Un swap deja como ruc_records una tabla FÍSICA distinta de la que
        // crearon las migraciones. Si se dejara así, el resto de la suite
        // trabajaría sobre esa tabla improvisada. Revertir el swap devuelve
        // la original con sus índices y su secuencia.
        $service = app(RucChunkedRestoreService::class);
        if ($service->tableExists(RucChunkedRestoreService::OLD_TABLE)) {
            $service->rollbackSwap();
        }

        // Las tablas de staging/old viven fuera de las migraciones, así que
        // DatabaseTruncation no las conoce: hay que limpiarlas a mano o
        // contaminarían el siguiente test.
        foreach ([RucChunkedRestoreService::STAGING_TABLE, RucChunkedRestoreService::OLD_TABLE] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table} CASCADE");
        }

        // DatabaseTruncation vacía las tablas al EMPEZAR cada test, no al
        // terminar: sin esto, las filas del último test de esta clase quedan
        // confirmadas y las clases siguientes (que usan RefreshDatabase) las
        // encuentran ahí. Se reinicia también la secuencia.
        DB::statement('TRUNCATE '.RucChunkedRestoreService::ACTIVE_TABLE.' RESTART IDENTITY CASCADE');

        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * NO se insertan ids explícitos: se dejan a la secuencia. Fijarlos a mano
     * no la hace avanzar, y como esta clase usa DatabaseTruncation (los datos
     * quedan confirmados), la siguiente clase de tests chocaría con
     * "duplicate key value violates unique constraint ruc_records_pkey".
     */
    private function seedRecords(int $count): void
    {
        DB::statement(
            "INSERT INTO ruc_records (ruc, razon_social, estado, condicion, ubigeo, departamento, provincia, distrito, direccion, created_at, updated_at)
             SELECT lpad((20000000000 + g)::text, 11, '0'),
                    'EMPRESA ' || g || ' S.A.C.',
                    CASE WHEN g % 7 = 0 THEN 'BAJA DEFINITIVA' ELSE 'ACTIVO' END,
                    CASE WHEN g % 5 = 0 THEN 'NO HABIDO' ELSE 'HABIDO' END,
                    '150101', 'LIMA', 'LIMA', 'MIRAFLORES',
                    'AV. SIEMPRE VIVA ' || g,
                    now(), now()
             FROM generate_series(1::bigint, ?::bigint) AS g",
            [$count]
        );
    }

    private function makeBackup(int $records, int $batchSize): RucBackup
    {
        $this->seedRecords($records);

        $backup = app(RucChunkedBackupService::class)->create(null, $batchSize);
        $this->tempFiles[] = $backup->absolutePath();

        return $backup;
    }

    private function newOperation(RucBackup $backup): RucBackupOperation
    {
        return RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_RUNNING,
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'progress' => 0,
            'message' => 'test',
            'started_at' => now(),
        ]);
    }

    // ------------------------------------------------------------ backup ---

    public function test_backup_produces_a_single_file_with_manifest_and_chunks(): void
    {
        $backup = $this->makeBackup(2500, 1000);

        $this->assertStringEndsWith('.'.RucBackupArchive::EXTENSION, $backup->name);
        $this->assertTrue($backup->isChunked());
        $this->assertSame(2500, (int) $backup->total_records);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($backup->absolutePath()) === true);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $zip->close();

        // Un solo archivo para el usuario; los lotes viven DENTRO.
        $this->assertContains('manifest.json', $entries);
        $this->assertContains('chunks/000001.csv.zst', $entries);
        $this->assertContains('chunks/000003.csv.zst', $entries);
        $this->assertCount(4, $entries); // 3 chunks + manifest
    }

    public function test_manifest_describes_every_chunk(): void
    {
        $backup = $this->makeBackup(2500, 1000);
        $manifest = RucBackupArchive::readManifest($backup->absolutePath());

        $this->assertSame(RucBackupArchive::FORMAT, $manifest['format']);
        $this->assertSame(1, $manifest['format_version']);
        $this->assertSame(2500, $manifest['total_records']);
        $this->assertSame(1000, $manifest['batch_size']);
        $this->assertSame(3, $manifest['total_batches']);
        $this->assertSame('zstd', $manifest['compression']);
        $this->assertSame(RucBackupArchive::COLUMNS, $manifest['columns']);
        $this->assertNotEmpty($manifest['chunks_sha256']);

        foreach ($manifest['chunks'] as $chunk) {
            foreach (['number', 'filename', 'records', 'uncompressed_size', 'compressed_size', 'sha256'] as $key) {
                $this->assertArrayHasKey($key, $chunk);
            }
            $this->assertGreaterThan(0, $chunk['compressed_size']);
            $this->assertGreaterThan(0, $chunk['uncompressed_size']);
            $this->assertSame(64, strlen((string) $chunk['sha256']));
        }
    }

    public function test_single_batch_when_records_fit_in_one_chunk(): void
    {
        $backup = $this->makeBackup(500, 1000);
        $manifest = RucBackupArchive::readManifest($backup->absolutePath());

        $this->assertSame(1, $manifest['total_batches']);
        $this->assertSame(500, $manifest['chunks'][0]['records']);
    }

    public function test_last_batch_is_partial(): void
    {
        $backup = $this->makeBackup(2500, 1000);
        $manifest = RucBackupArchive::readManifest($backup->absolutePath());

        $this->assertSame(1000, $manifest['chunks'][0]['records']);
        $this->assertSame(1000, $manifest['chunks'][1]['records']);
        $this->assertSame(500, $manifest['chunks'][2]['records'], 'El último lote debe quedar incompleto.');
    }

    // -------------------------------------------------------- validación ---

    public function test_manifest_with_chunk_count_mismatch_is_rejected(): void
    {
        $manifest = [
            'format' => RucBackupArchive::FORMAT,
            'format_version' => 1,
            'columns' => RucBackupArchive::COLUMNS,
            'total_records' => 10,
            'total_batches' => 5,
            'chunks' => [['number' => 1, 'records' => 10]],
        ];

        $this->expectExceptionMessageMatches('/declara 5 lotes pero lista 1/');
        RucBackupArchive::assertManifestIsValid($manifest);
    }

    public function test_manifest_with_gap_in_chunk_numbering_is_rejected(): void
    {
        $manifest = [
            'format' => RucBackupArchive::FORMAT,
            'format_version' => 1,
            'columns' => RucBackupArchive::COLUMNS,
            'total_records' => 20,
            'total_batches' => 2,
            'chunks' => [['number' => 1, 'records' => 10], ['number' => 3, 'records' => 10]],
        ];

        $this->expectExceptionMessageMatches('/huecos o duplicados/');
        RucBackupArchive::assertManifestIsValid($manifest);
    }

    public function test_unsupported_format_version_is_rejected(): void
    {
        $this->expectExceptionMessageMatches('/no soportada/');
        RucBackupArchive::assertManifestIsValid([
            'format' => RucBackupArchive::FORMAT,
            'format_version' => 99,
            'columns' => RucBackupArchive::COLUMNS,
            'total_records' => 0,
            'total_batches' => 0,
            'chunks' => [],
        ]);
    }

    public function test_column_mismatch_is_rejected(): void
    {
        $this->expectExceptionMessageMatches('/columnas del backup no coinciden/');
        RucBackupArchive::assertManifestIsValid([
            'format' => RucBackupArchive::FORMAT,
            'format_version' => 1,
            'columns' => ['id', 'ruc'],
            'total_records' => 0,
            'total_batches' => 0,
            'chunks' => [],
        ]);
    }

    public function test_corrupt_chunk_checksum_is_detected(): void
    {
        $backup = $this->makeBackup(2000, 1000);
        $manifest = RucBackupArchive::readManifest($backup->absolutePath());

        // Se altera el sha256 declarado: equivale a un chunk manipulado.
        $manifest['chunks'][1]['sha256'] = str_repeat('0', 64);

        $this->expectExceptionMessageMatches('/checksum del chunk .* no coincide/');
        RucBackupArchive::verifyChunks($backup->absolutePath(), $manifest);
    }

    public function test_missing_chunk_is_detected(): void
    {
        $backup = $this->makeBackup(2000, 1000);
        $manifest = RucBackupArchive::readManifest($backup->absolutePath());
        $manifest['chunks'][1]['filename'] = 'chunks/999999.csv.zst';

        $this->expectExceptionMessageMatches('/Falta el chunk/');
        RucBackupArchive::verifyChunks($backup->absolutePath(), $manifest);
    }

    // --------------------------------------------------------- restore ----

    public function test_restore_replaces_data_and_keeps_the_previous_table(): void
    {
        $backup = $this->makeBackup(2500, 1000);

        DB::statement("UPDATE ruc_records SET razon_social = 'ALTERADO' WHERE id % 2 = 0");
        DB::statement('DELETE FROM ruc_records WHERE id % 5 = 0');
        $this->assertSame(2000, (int) DB::table('ruc_records')->count());

        $result = app(RucChunkedRestoreService::class)->restore($backup, $this->newOperation($backup));

        $this->assertSame(2500, $result['records_restored']);
        $this->assertSame(2500, (int) DB::table('ruc_records')->count());
        $this->assertSame(0, (int) DB::table('ruc_records')->where('razon_social', 'ALTERADO')->count());
        $this->assertSame(2500, (int) DB::table(RucChunkedRestoreService::OLD_TABLE)->count() + 500);
    }

    public function test_restore_rebuilds_canonical_indexes(): void
    {
        $backup = $this->makeBackup(1500, 1000);
        app(RucChunkedRestoreService::class)->restore($backup, $this->newOperation($backup));

        $indexes = collect(DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'ruc_records' AND schemaname = current_schema()"
        ))->pluck('indexname')->all();

        foreach (['ruc_records_pkey', 'ruc_records_ruc_unique', 'ruc_records_estado_index', 'ruc_records_razon_social_trgm_index'] as $expected) {
            $this->assertContains($expected, $indexes, "Falta el índice {$expected} tras el swap.");
        }
    }

    /**
     * PRUEBA CRÍTICA. Si un lote intermedio falla, ruc_records debe quedar
     * exactamente como estaba: los datos se cargan en la tabla de staging y
     * el swap nunca llega a ejecutarse.
     */
    public function test_failure_in_a_middle_batch_leaves_the_active_table_untouched(): void
    {
        $backup = $this->makeBackup(3000, 1000);

        $before = (int) DB::table('ruc_records')->count();
        $sample = DB::table('ruc_records')->orderBy('id')->first();

        $this->failOnCopyNumber(2);

        try {
            app(RucChunkedRestoreService::class)->restore($backup, $this->newOperation($backup));
            $this->fail('El restore debía fallar en el lote 2.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Falló la carga del lote 2', $e->getMessage());
        }

        $this->assertSame($before, (int) DB::table('ruc_records')->count(), 'ruc_records cambió de tamaño pese al fallo.');
        $this->assertEquals($sample, DB::table('ruc_records')->orderBy('id')->first(), 'ruc_records cambió de contenido pese al fallo.');
        $this->assertSame(3000, $before);
    }

    public function test_resume_continues_from_the_checkpoint_instead_of_restarting(): void
    {
        $backup = $this->makeBackup(3000, 1000);
        $operation = $this->newOperation($backup);

        // Primer intento: muere en el lote 3, con 1 y 2 ya confirmados.
        $this->failOnCopyNumber(3);
        try {
            app(RucChunkedRestoreService::class)->restore($backup, $operation);
            $this->fail('Debía fallar en el lote 3.');
        } catch (\RuntimeException) {
            // esperado
        }

        $operation->refresh();
        $this->assertSame(2, $operation->checkpoint['batch']);
        $this->assertSame(2000, (int) DB::table(RucChunkedRestoreService::STAGING_TABLE)->count());

        // Segundo intento con --resume: solo debe copiar el lote que falta.
        $this->restoreRealRunner();
        $copies = $this->countCopies();

        $result = app(RucChunkedRestoreService::class)->restore($backup, $operation, resume: true);

        $this->assertSame(3000, $result['records_restored']);
        $this->assertSame(3000, (int) DB::table('ruc_records')->count());
        $this->assertSame(1, $copies(), 'Al reanudar solo debe copiarse el lote pendiente, no los 3.');
    }

    public function test_resume_rejects_a_different_backup(): void
    {
        $backup = $this->makeBackup(2000, 1000);
        $operation = $this->newOperation($backup);

        $this->failOnCopyNumber(2);
        try {
            app(RucChunkedRestoreService::class)->restore($backup, $operation);
        } catch (\RuntimeException) {
            // esperado
        }
        $this->restoreRealRunner();

        // Otro backup distinto (mismo contenido, otro archivo => otro chunks_sha256
        // no necesariamente, así que se fuerza el checkpoint a otro valor).
        $operation->refresh();
        $checkpoint = $operation->checkpoint;
        $checkpoint['chunks_sha256'] = 'otro-backup-distinto';
        $operation->update(['checkpoint' => $checkpoint]);

        $this->expectExceptionMessageMatches('/no coincide con el de la operación interrumpida/');
        app(RucChunkedRestoreService::class)->restore($backup, $operation, resume: true);
    }

    public function test_resume_rejects_inconsistent_staging_row_count(): void
    {
        $backup = $this->makeBackup(3000, 1000);
        $operation = $this->newOperation($backup);

        $this->failOnCopyNumber(3);
        try {
            app(RucChunkedRestoreService::class)->restore($backup, $operation);
        } catch (\RuntimeException) {
            // esperado
        }
        $this->restoreRealRunner();

        // Alguien tocó el staging entre medias: reanudar sería inseguro.
        DB::statement('DELETE FROM '.RucChunkedRestoreService::STAGING_TABLE.' WHERE id % 2 = 0');

        $this->expectExceptionMessageMatches('/estado es inconsistente/');
        app(RucChunkedRestoreService::class)->restore($backup, $operation->refresh(), resume: true);
    }

    public function test_cancellation_stops_after_the_current_batch_and_preserves_the_active_table(): void
    {
        $backup = $this->makeBackup(3000, 1000);
        $operation = $this->newOperation($backup);
        $before = (int) DB::table('ruc_records')->count();

        // Se pide la cancelación tras confirmar el primer lote.
        $this->app->bind(RucBackupProcessRunner::class, function () use ($operation) {
            return new class($operation) extends RucBackupProcessRunner
            {
                public function __construct(private readonly RucBackupOperation $operation) {}

                public function run(Process $process): void
                {
                    $process->run();
                    $this->operation->update(['cancel_requested_at' => now()]);
                }
            };
        });

        $result = app(RucChunkedRestoreService::class)->restore($backup, $operation);

        $this->assertTrue($result['cancelled']);
        $this->assertSame(2, $result['stopped_before_batch']);
        $this->assertSame($before, (int) DB::table('ruc_records')->count(), 'ruc_records no debe tocarse al cancelar.');
        $this->assertSame(RucBackupOperation::STATUS_CANCELLED, $operation->refresh()->status);
        // El staging se conserva para poder reanudar.
        $this->assertSame(1000, (int) DB::table(RucChunkedRestoreService::STAGING_TABLE)->count());
    }

    public function test_rollback_restores_the_previous_dataset(): void
    {
        $backup = $this->makeBackup(2000, 1000);

        DB::statement('DELETE FROM ruc_records WHERE id > 1200');
        $beforeRestore = (int) DB::table('ruc_records')->count();

        $service = app(RucChunkedRestoreService::class);
        $service->restore($backup, $this->newOperation($backup));
        $this->assertSame(2000, (int) DB::table('ruc_records')->count());

        $service->rollbackSwap();

        $this->assertSame($beforeRestore, (int) DB::table('ruc_records')->count());
        $indexes = collect(DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'ruc_records' AND schemaname = current_schema()"
        ))->pluck('indexname')->all();
        $this->assertContains('ruc_records_pkey', $indexes);
    }

    /**
     * Regresión: `CREATE TABLE (LIKE ... INCLUDING DEFAULTS)` copia el DEFAULT
     * `nextval(...)` pero NO la pertenencia de la secuencia. Sin reasignarla,
     * la secuencia se queda colgando de ruc_records_old y el primer
     * `DROP TABLE ... CASCADE` se la lleva por delante, dejando la tabla
     * activa sin poder insertar.
     */
    public function test_swap_reassigns_the_id_sequence_to_the_new_table(): void
    {
        $backup = $this->makeBackup(1500, 1000);
        app(RucChunkedRestoreService::class)->restore($backup, $this->newOperation($backup));

        $owner = DB::selectOne(
            "SELECT t.relname AS table_name
             FROM pg_class s
             JOIN pg_depend d ON d.objid = s.oid AND d.deptype = 'a'
             JOIN pg_class t ON t.oid = d.refobjid
             WHERE s.relkind = 'S' AND s.relname = 'ruc_records_id_seq'"
        );

        $this->assertSame('ruc_records', $owner?->table_name, 'La secuencia debe pertenecer a la tabla activa tras el swap.');

        // Al soltar la tabla antigua la secuencia debe sobrevivir…
        DB::statement('DROP TABLE IF EXISTS '.RucChunkedRestoreService::OLD_TABLE.' CASCADE');

        // …y la tabla activa debe seguir aceptando inserciones sin chocar
        // con la clave primaria (setval se colocó por encima del mayor id).
        DB::table('ruc_records')->insert([
            'ruc' => '99999999999',
            'razon_social' => 'POST SWAP S.A.C.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1501, (int) DB::table('ruc_records')->count());
    }

    public function test_restore_rejects_a_backup_whose_chunk_count_does_not_match(): void
    {
        $backup = $this->makeBackup(2000, 1000);

        // Se reescribe el manifest declarando un lote de más.
        $path = $backup->absolutePath();
        $manifest = RucBackupArchive::readManifest($path);
        $manifest['total_batches'] = 3;

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        $before = (int) DB::table('ruc_records')->count();

        try {
            app(RucChunkedRestoreService::class)->restore($backup, $this->newOperation($backup));
            $this->fail('Debía rechazarse el manifest incoherente.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('declara 3 lotes pero lista 2', $e->getMessage());
        }

        $this->assertSame($before, (int) DB::table('ruc_records')->count());
        $this->assertFalse(
            app(RucChunkedRestoreService::class)->tableExists(RucChunkedRestoreService::STAGING_TABLE),
            'Un manifest inválido no debe llegar a crear la tabla de staging.'
        );
    }

    // ------------------------------------------------------------ utils ---

    /** Sustituye el runner para que el COPY número N falle. */
    private function failOnCopyNumber(int $failAt): void
    {
        $this->app->bind(RucBackupProcessRunner::class, fn () => new class($failAt) extends RucBackupProcessRunner
        {
            private int $copies = 0;

            public function __construct(private readonly int $failAt) {}

            public function run(Process $process): void
            {
                // Solo se cuentan los COPY del restore (llevan FROM STDIN);
                // los del backup no deben verse afectados.
                if (str_contains((string) $process->getCommandLine(), 'FROM STDIN')) {
                    $this->copies++;
                    if ($this->copies === $this->failAt) {
                        // Se sustituye la orden por una que falla, en vez de
                        // romper PostgreSQL: así el fallo es determinista y
                        // el servicio recorre su camino de error REAL
                        // (isSuccessful() falso -> excepción con el número de
                        // lote). Symfony 6 quitó setCommandLine(), de ahí la
                        // reflexión.
                        $property = new \ReflectionProperty(Process::class, 'commandline');
                        $property->setValue($process, 'printf "fallo simulado del lote" >&2; exit 1');
                    }
                }

                $process->run();
            }
        });
    }

    private function restoreRealRunner(): void
    {
        $this->app->bind(RucBackupProcessRunner::class, fn () => new RucBackupProcessRunner);
    }

    /** @return callable(): int */
    private function countCopies(): callable
    {
        $counter = new class
        {
            public int $count = 0;
        };

        $this->app->bind(RucBackupProcessRunner::class, fn () => new class($counter) extends RucBackupProcessRunner
        {
            public function __construct(private readonly object $counter) {}

            public function run(Process $process): void
            {
                if (str_contains((string) $process->getCommandLine(), 'FROM STDIN')) {
                    $this->counter->count++;
                }
                $process->run();
            }
        });

        return static fn (): int => $counter->count;
    }
}
