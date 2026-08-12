<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Services\RucBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pruebas que previenen restauraciones concurrentes/simultáneas.
 *
 * El sistema debe rechazar un POST de restore si ya hay una operación active.
 * Esto previene:
 * - Race conditions en safety backup
 * - Múltiples TRUNCATE en paralelo
 * - Bloqueos de lock
 */
class RestoreConcurrencyTest extends TestCase
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
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrador', 'description' => 'Acceso total al sistema']
        );
        $user->roles()->attach($role);

        return $user;
    }

    private function realBackup(User $user): RucBackup
    {
        RucRecord::query()->create([
            'ruc' => '20123456789',
            'razon_social' => 'EMPRESA CONCURRENCIA S.A.C.',
        ]);

        return app(RucBackupService::class)->create($user);
    }

    public function test_restore_rejected_when_other_restore_pending(): void
    {
        // Crear primera operación en estado PENDING con un backup válido
        $existingBackup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $existingBackup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_PENDING,
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'progress' => 0,
            'message' => 'En cola',
        ]);

        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $user = $this->adminUser();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('error');

        // No se creó segunda operación
        $this->assertSame(1, RucBackupOperation::count());
    }

    public function test_restore_rejected_when_other_restore_running(): void
    {
        // Crear primera operación en estado RUNNING con un backup válido
        $existingBackup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $existingBackup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_RUNNING,
            'stage' => RucBackupOperation::STAGE_PREPARING_RESTORE,
            'progress' => 45,
            'message' => 'Preparando...',
        ]);

        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $user = $this->adminUser();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('error');

        // No se creó segunda operación
        $this->assertSame(1, RucBackupOperation::count());
    }

    public function test_restore_allowed_when_previous_restore_completed(): void
    {
        // Crear primera operación COMPLETADA con un backup válido
        $existingBackup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $existingBackup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_COMPLETED,
            'stage' => RucBackupOperation::STAGE_COMPLETED,
            'progress' => 100,
            'message' => 'Completada',
            'finished_at' => now(),
        ]);

        $user = $this->adminUser();
        $backup = $this->realBackup($user);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        // Debe permitir second restore
        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('success');

        // Se crearon 2 operaciones (primera completada + nueva completada en testing)
        $this->assertSame(2, RucBackupOperation::count());
        $newOperation = RucBackupOperation::query()->latest('id')->firstOrFail();
        $this->assertSame(RucBackupOperation::STATUS_COMPLETED, $newOperation->status);
        $this->assertSame(RucBackupOperation::STAGE_COMPLETED, $newOperation->stage);
    }

    public function test_restore_allowed_when_previous_restore_failed(): void
    {
        // Crear primera operación FALLIDA con un backup válido
        $existingBackup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $existingBackup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_FAILED,
            'stage' => RucBackupOperation::STAGE_VERIFYING_CHECKSUM,
            'progress' => 15,
            'message' => 'Falló',
            'error_message' => 'Checksum inválido',
            'finished_at' => now(),
        ]);

        $user = $this->adminUser();
        $backup = $this->realBackup($user);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        // Debe permitir reintentar
        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('success');

        // Se crearon 2 operaciones (primera failed + nueva completada en testing)
        $this->assertSame(2, RucBackupOperation::count());
    }

    public function test_multiple_concurrent_restores_rejected(): void
    {
        // Simular 3 intentos simultáneos
        $user = $this->adminUser();
        $backup = $this->realBackup($user);

        RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_RUNNING,
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'progress' => 50,
            'message' => 'En curso...',
        ]);

        // Primer intento: debe ser rechazado por la operación activa manual
        $response1 = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));
        $response1->assertRedirect();
        $response1->assertSessionHas('error');
        $this->assertSame(1, RucBackupOperation::count());

        // Segundo intento: debe seguir rechazado
        $response2 = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));
        $response2->assertRedirect();
        $response2->assertSessionHas('error');
        $this->assertSame(1, RucBackupOperation::count());

        // Tercer intento: debe seguir rechazado
        $response3 = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));
        $response3->assertRedirect();
        $response3->assertSessionHas('error');
        $this->assertSame(1, RucBackupOperation::count());
    }

    public function test_operation_status_endpoint_reflects_active_restore(): void
    {
        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $operation = RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $backup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_RUNNING,
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'progress' => 60,
            'message' => 'En progreso...',
        ]);

        $user = $this->adminUser();

        $response = $this->actingAs($user)->get(route('admin.ruc.backups.operations.status', ['operation' => $operation->uuid]));

        $response->assertOk();
        $data = $response->json();

        // Verifica que el endpoint retorna datos de la operación activa
        $this->assertSame(RucBackupOperation::STATUS_RUNNING, $data['status']);
        $this->assertSame(RucBackupOperation::STAGE_RESTORING, $data['stage']);
        $this->assertSame(60, $data['progress']);
        $this->assertSame('En progreso...', $data['message']);
    }

    public function test_inactive_restore_does_not_block_new_restore(): void
    {
        // Crear operación inactiva (cancelled) con un backup válido
        $existingBackup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        RucBackupOperation::create([
            'uuid' => (string) Str::uuid(),
            'backup_id' => $existingBackup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_FAILED,  // Inactiva
            'stage' => RucBackupOperation::STAGE_QUEUED,
            'progress' => 5,
            'message' => 'Cancelada',
        ]);

        $user = $this->adminUser();
        $backup = $this->realBackup($user);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        // Debe permitir restore porque no hay uno ACTIVO
        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('success');

        // Se crearon 2 operaciones
        $this->assertSame(2, RucBackupOperation::count());
    }
}
