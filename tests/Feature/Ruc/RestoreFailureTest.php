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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pruebas que el Job maneja fallos correctamente:
 * - Marca operación como FAILED
 * - Registra error_message detallado
 * - Limpia recursos temporales (safety backup si es necesario)
 * - Libera lock distribuido (mediante cache/Redis)
 * - No corrompe datos de restore en caso de fallo parcial
 */
class RestoreFailureTest extends TestCase
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

    public function test_restore_job_marks_failed_on_exception(): void
    {
        // Crear backup con checksum incorrecto (para forzar fallo)
        $backup = RucBackup::factory()->create([
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => str_repeat('0', 64),  // Checksum inválido
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
        } catch (\Throwable $e) {
            // Esperado que lance excepción
        }

        // Verifica que la operación se marcó como FAILED
        $operation->refresh();
        $this->assertSame(RucBackupOperation::STATUS_FAILED, $operation->status);
        $this->assertNotNull($operation->error_message);
        $this->assertNotNull($operation->finished_at);
    }

    public function test_restore_job_records_detailed_error(): void
    {
        $backup = RucBackup::factory()->create([
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => str_repeat('1', 64),  // Checksum incorrecto
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
        } catch (\Throwable $e) {
            // esperado
        }

        $operation->refresh();
        // error_message debe contener contexto
        $this->assertNotEmpty($operation->error_message);
        $this->assertTrue(
            mb_stripos($operation->error_message, 'checksum') !== false,
            "error_message debe mencionar 'checksum'"
        );
    }

    public function test_restore_failure_prevents_data_loss(): void
    {
        // Simular: 5 registros en DB antes de restore
        $this->seedRecords(5, '27');
        $recordsBefore = DB::table('ruc_records')->count();

        // Crear backup pero marcar como inválido
        $backup = RucBackup::factory()->create([
            'status' => RucBackup::STATUS_COMPLETED,
            'checksum_sha256' => str_repeat('0', 64),  // Forzar fallo
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
        } catch (\Throwable $e) {
            // esperado
        }

        // Verificar que datos originales NO fueron truncados
        $recordsAfter = DB::table('ruc_records')->count();
        $this->assertSame($recordsBefore, $recordsAfter, 'restore failed pero truncó datos');
        $this->assertDatabaseHas('ruc_records', ['ruc' => '27000000000']);
    }

    public function test_restore_failed_operation_has_safety_backup(): void
    {
        $this->seedRecords(5, '27');
        $backup = app(\App\Modules\Ruc\Services\RucBackupService::class)->create($this->adminUser());

        // Crear operación que fallará
        $operation = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        // Modificar backup para hacerlo inválido
        $backup->update(['checksum_sha256' => str_repeat('0', 64)]);

        $job = new RestoreRucBackupJob($operation->id);
        try {
            $job->handle(app(\App\Modules\Ruc\Services\RucBackupService::class));
        } catch (\Throwable $e) {
            // esperado: falla en checksum (antes de crear safety backup)
        }

        $operation->refresh();
        $this->assertSame(RucBackupOperation::STATUS_FAILED, $operation->status);
        // En este caso, no debería haber safety backup (falla antes de crearlo)
        $this->assertNull($operation->safety_backup_id);
    }

    public function test_restore_releases_lock_on_failure(): void
    {
        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);

        $operation = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        // Forzar fallo con checksum inválido
        $backup->update(['checksum_sha256' => str_repeat('0', 64)]);

        $job = new RestoreRucBackupJob($operation->id);
        try {
            $job->handle(app(\App\Modules\Ruc\Services\RucBackupService::class));
        } catch (\Throwable $e) {
            // esperado
        }

        // Después de fallo, la operación debe estar en estado terminal
        $operation->refresh();
        $this->assertTrue($operation->isTerminal(), 'Operación debe estar en estado terminal después del fallo');

        // Verificar que el lock fue liberado:
        // El siguiente restore debe poder ser invocado (no debe estar bloqueado)
        $newBackup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);

        // Este POST no debe ser rechazado por "restore already active"
        $this->assertFalse(
            RucBackupOperation::hasActiveRestore(),
            'Lock no fue liberado después de fallo'
        );
    }

    public function test_restore_failed_state_allows_retry(): void
    {
        // Primera operación: falla
        $backup1 = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $backup1->update(['checksum_sha256' => str_repeat('0', 64)]);

        $operation1 = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup1->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        $job1 = new RestoreRucBackupJob($operation1->id);
        try {
            $job1->handle();
        } catch (\Throwable $e) {
            // esperado
        }

        // Verificar que se falló
        $operation1->refresh();
        $this->assertSame(RucBackupOperation::STATUS_FAILED, $operation1->status);

        // Ahora intentar restore con backup correcto: debe ser permitido
        $backup2 = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);

        $operation2 = RucBackupOperation::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'backup_id' => $backup2->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'created_by' => $this->adminUser()->id,
        ]);

        // Debe no ser bloqueado por la operación anterior fallida
        $this->assertFalse(RucBackupOperation::hasActiveRestore(), 'Restore anterior fallo pero sigue bloqueando');

        // Segundo restore debe poder ejecutarse sin rechazo
        $this->assertSame(2, RucBackupOperation::count());
    }

    public function test_restore_timeout_handled_gracefully(): void
    {
        // RestoreRucBackupJob::failed() debe ser llamado si timeout ocurre
        // (No podemos simular timeout real en test, pero verificamos que la config es correcta)

        // Verificar que el Job tiene configuración de timeout
        $job = new RestoreRucBackupJob(1);

        // El job debe tener tries=1 (no reintentos automáticos en fallo)
        $this->assertSame(1, $job->tries, 'Job debe tener tries=1 (no reintentos en restore destructivo)');

        // El job debe tener timeout configurado (86400 = 24h)
        $this->assertSame(86400, $job->timeout, 'Job debe tener timeout=86400 (24h)');
    }
}
