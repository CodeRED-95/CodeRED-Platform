<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Jobs\RestoreRucBackupJob;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Models\RucRecord;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas que RestoreRucBackupJob ejecuta correctamente las 9 etapas de restore:
 *
 * 1. queued (inicial)
 * 2. validating_backup (verificar archivo existe, formato válido)
 * 3. verifying_checksum (verificar SHA-256)
 * 4. creating_safety_backup (pg_dump del estado actual)
 * 5. validating_safety_backup (verificar safety backup es válido)
 * 6. preparing_restore (comprobar espacio en disco)
 * 7. restoring (TRUNCATE + COPY dentro de transacción PostgreSQL)
 * 8. verifying_restore (verificar records_after)
 * 9. completed (finalizar)
 *
 * También verifica:
 * - ANALYZE se ejecuta post-restore
 * - RucStatisticsService actualiza tabla + invalida caches
 * - operation.status = completed después
 * - safety_backup se creó y está linked
 */
class RestoreRucBackupJobTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    private function seedRecords(int $count, string $prefix): void
    {
        for ($i = 0; $i < $count; $i++) {
            RucRecord::create([
                'ruc' => $prefix.str_pad((string) $i, 9, '0', STR_PAD_LEFT),
                'razon_social' => "EMPRESA {$i}",
            ]);
        }
    }

    public function test_restore_job_completes_all_stages(): void
    {
        // Preparar: crear backup del estado actual
        $this->seedRecords(5, '27');
        $backup = app(\App\Modules\Ruc\Services\RucBackupService::class)->create($this->adminUser());

        // Cambiar estado de RUC (simular cambios entre backup y restore)
        RucRecord::query()->delete();
        $this->seedRecords(3, '20');

        // Crear operación y despachar job
        $operation = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'progress' => 0,
            'message' => 'En cola',
            'created_by' => $this->adminUser()->id,
        ]);

        // Ejecutar el Job directamente (en test, no en la cola)
        $job = new RestoreRucBackupJob($operation->id);
        $job->handle(app(\App\Modules\Ruc\Services\RucBackupService::class));

        // Verificar operation se completó
        $operation->refresh();
        $this->assertSame(RucBackupOperation::STATUS_COMPLETED, $operation->status);
        $this->assertSame(RucBackupOperation::STAGE_COMPLETED, $operation->stage);
        $this->assertSame(100, $operation->progress);
        $this->assertNotNull($operation->finished_at);
        $this->assertNotNull($operation->duration_seconds);

        // Verificar datos fueron restaurados
        $this->assertSame(5, DB::table('ruc_records')->count());
        $this->assertDatabaseHas('ruc_records', ['ruc' => '27000000000']);
        $this->assertDatabaseMissing('ruc_records', ['ruc' => '20000000000']);

        // Verificar safety backup fue creado
        $this->assertNotNull($operation->safety_backup_id);
        $safety = RucBackup::findOrFail($operation->safety_backup_id);
        $this->assertSame(RucBackup::TYPE_SAFETY, $safety->backup_type);
        $this->assertTrue($safety->isCompleted());

        // Verificar records_before/after registrados
        $this->assertSame(3, $operation->records_before);
        $this->assertSame(5, $operation->records_after);
    }

    public function test_restore_job_rejects_corrupted_backup(): void
    {
        $backup = RucBackup::factory()->create([
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => str_repeat('0', 64),  // Checksum falso
        ]);

        $operation = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        $job = new RestoreRucBackupJob($operation->id);
        try {
            $job->handle(app(\App\Modules\Ruc\Services\RucBackupService::class));
            $this->fail('Expected exception for corrupted backup');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('checksum', mb_strtolower($e->getMessage()));
        }

        // Operación marcada como failed
        $operation->refresh();
        $this->assertSame(RucBackupOperation::STATUS_FAILED, $operation->status);
        $this->assertNotNull($operation->error_message);
    }

    public function test_restore_job_validates_safety_backup(): void
    {
        $this->seedRecords(5, '27');
        $backup = app(\App\Modules\Ruc\Services\RucBackupService::class)->create($this->adminUser());

        // Crear operación con un safety_backup vacío/malo (simulado)
        $operation = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        // Modificar backup para hacerlo inválido (tamaño cero)
        RucRecord::query()->delete();
        $badBackup = app(\App\Modules\Ruc\Services\RucBackupService::class)->createSafetyBackup();
        // Truncate: simula un safety backup vacío (lo cual es problema si records_before > 0)

        $job = new RestoreRucBackupJob($operation->id);
        try {
            // Debe fallar porque records_before=5 pero safety será 0
            $job->handle();
            $this->fail('Expected exception for invalid safety backup');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('safety', mb_strtolower($e->getMessage()));
        }

        $operation->refresh();
        $this->assertSame(RucBackupOperation::STATUS_FAILED, $operation->status);
    }

    public function test_restore_job_updates_statistics(): void
    {
        $this->seedRecords(5, '27');
        $backup = app(\App\Modules\Ruc\Services\RucBackupService::class)->create($this->adminUser());

        $operation = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        // Pre-restore: clear caches
        Cache::forget('ruc:records:count');
        Cache::forget('dashboard:ruc');

        $job = new RestoreRucBackupJob($operation->id);
        $job->handle();

        // Post-restore: RucStatisticsService debe haber actualizado tabla + invalidado caches
        // Verificar tabla
        $stats = DB::table('ruc_statistics')->first();
        $this->assertSame(5, $stats->total_records);
        $this->assertNotNull($stats->last_analyzed_at);  // ANALYZE se ejecutó

        // Verificar caches fueron invalidados (pero no vueltos a llenar automáticamente)
        $this->assertNull(Cache::get('ruc:records:count'));  // Debe recalcularse en siguiente request
    }

    public function test_restore_job_creates_no_duplicate_safety_backups(): void
    {
        $this->seedRecords(5, '27');
        $backup = app(\App\Modules\Ruc\Services\RucBackupService::class)->create($this->adminUser());

        $operation = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        $safetyCountBefore = RucBackup::where('backup_type', RucBackup::TYPE_SAFETY)->count();

        $job = new RestoreRucBackupJob($operation->id);
        $job->handle();

        // Exactamente un safety backup nuevo
        $safetyCountAfter = RucBackup::where('backup_type', RucBackup::TYPE_SAFETY)->count();
        $this->assertSame($safetyCountBefore + 1, $safetyCountAfter);

        $operation->refresh();
        $this->assertNotNull($operation->safety_backup_id);
    }
}
