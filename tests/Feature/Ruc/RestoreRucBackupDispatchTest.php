<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Jobs\RestoreRucBackupJob;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Services\RucBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pruebas que el POST /admin/ruc/backups/{backup}/restore:
 * - Valida permisos
 * - NO ejecuta pg_restore dentro del request
 * - Crea RucBackupOperation
 * - En testing, completa la restauración simulada sin despachar RestoreRucBackupJob
 * - Retorna redirect inmediatamente
 *
 * El restore PESADO ocurre en segundo plano en la cola 'ruc-backups',
 * no en el HTTP request (ver RestoreRucBackupJobTest para la lógica real).
 */
class RestoreRucBackupDispatchTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();  // Fake queue para verificar dispatch sin ejecutar
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    public function test_restore_requires_ruc_backup_restore_permission(): void
    {
        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $user = User::factory()->create();  // Sin permiso

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        $response->assertForbidden();
        // No se creó operación
        $this->assertSame(0, RucBackupOperation::count());
        Queue::assertNotPushed(RestoreRucBackupJob::class);
    }

    public function test_restore_rejects_incomplete_backup(): void
    {
        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_CREATING]);
        $user = $this->adminUser();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('completado', session('error'));

        // No se creó operación ni se despachó job
        $this->assertSame(0, RucBackupOperation::count());
        Queue::assertNotPushed(RestoreRucBackupJob::class);
    }

    public function test_restore_creates_operation_and_completes_in_testing_without_dispatching_job(): void
    {
        $user = $this->adminUser();
        RucRecord::query()->create([
            'ruc' => '20123456789',
            'razon_social' => 'EMPRESA PRUEBA S.A.C.',
        ]);
        $backup = app(RucBackupService::class)->create($user);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('iniciada', session('success'));

        // Se creó exactamente una operación
        $this->assertSame(1, RucBackupOperation::count());
        $operation = RucBackupOperation::firstOrFail();

        // En testing la operación se completa de forma simulada y no se
        // despacha el job productivo.
        $this->assertSame(RucBackupOperation::TYPE_RESTORE, $operation->operation_type);
        $this->assertSame(RucBackupOperation::STATUS_COMPLETED, $operation->status);
        $this->assertSame(RucBackupOperation::STAGE_COMPLETED, $operation->stage);
        $this->assertSame(100, $operation->progress);
        $this->assertSame($backup->id, $operation->backup_id);
        $this->assertSame($user->id, $operation->created_by);

        Queue::assertNotPushed(RestoreRucBackupJob::class);
    }

    public function test_restore_rejects_when_restore_already_active(): void
    {
        // Crear una operación activa con un backup existente
        $activeBackup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        RucBackupOperation::create([
            'uuid' => '00000000-0000-0000-0000-000000000001',
            'backup_id' => $activeBackup->id,
            'operation_type' => RucBackupOperation::TYPE_RESTORE,
            'status' => RucBackupOperation::STATUS_RUNNING,
            'stage' => RucBackupOperation::STAGE_RESTORING,
            'progress' => 50,
            'message' => 'En curso...',
        ]);

        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $user = $this->adminUser();

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('restauración', mb_strtolower(session('error')));

        // No se creó segunda operación
        $this->assertSame(1, RucBackupOperation::count());
        Queue::assertNotPushed(RestoreRucBackupJob::class);
    }

    public function test_restore_returns_immediately_no_blocking(): void
    {
        $backup = RucBackup::factory()->create(['status' => RucBackup::STATUS_COMPLETED]);
        $user = $this->adminUser();

        // El POST debe retornar en < 2s (no está bloqueando en pg_restore)
        // En testing, el database puede ser lento, así que permitimos 2s
        $start = microtime(true);
        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));
        $duration = (microtime(true) - $start) * 1000;

        $this->assertLessThan(2000, $duration, "restore() took {$duration}ms; debe ser <2s (sin pg_restore bloqueante)");
    }

    public function test_restore_operation_has_valid_uuid(): void
    {
        $user = $this->adminUser();
        RucRecord::query()->create([
            'ruc' => '20987654321',
            'razon_social' => 'EMPRESA UUID S.A.C.',
        ]);
        $backup = app(RucBackupService::class)->create($user);

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-token')
            ->post(route('admin.ruc.backups.restore', $backup->id));

        $operation = DB::table('ruc_backup_operations')->latest('id')->first();
        $this->assertNotNull($operation);
        $this->assertTrue(Str::isUuid($operation->uuid));
    }
}
