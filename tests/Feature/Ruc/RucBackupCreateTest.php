<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RucBackupCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // CSRF tiene su propia prueba dedicada (RucBackupCsrfTest); aquí se
        // prueba la lógica de creación de backups.
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    public function test_create_backup_requires_authentication(): void
    {
        $response = $this->post(route('admin.ruc.backups.store'));

        $response->assertRedirect(route('login'));
    }

    public function test_create_backup_requires_permission(): void
    {
        $user = User::factory()->create(); // sin rol/permiso

        $response = $this->actingAs($user)->post(route('admin.ruc.backups.store'));

        $response->assertForbidden();
    }

    public function test_create_backup_generates_a_real_file_and_completes(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->post(route('admin.ruc.backups.store'));

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('success');

        $backup = RucBackup::latest('id')->first();
        $this->assertNotNull($backup);
        $this->assertSame(RucBackup::STATUS_COMPLETED, $backup->status);
        $this->assertTrue($backup->fileExists());
        $this->assertGreaterThan(0, $backup->file_size_bytes);
    }

    public function test_created_dump_passes_pg_restore_list_and_contains_ruc_records(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user)->post(route('admin.ruc.backups.store'));

        $backup = RucBackup::latest('id')->first();

        $list = new Process(['pg_restore', '--list', $backup->absolutePath()]);
        $list->run();

        $this->assertTrue($list->isSuccessful(), 'pg_restore --list debe poder leer el archivo generado');
        $this->assertStringContainsString('ruc_records', $list->getOutput());
    }

    public function test_created_backup_has_valid_checksum(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user)->post(route('admin.ruc.backups.store'));

        $backup = RucBackup::latest('id')->first();

        $this->assertSame(hash_file('sha256', $backup->absolutePath()), $backup->checksum_sha256);
    }

    public function test_created_backup_uses_relative_storage_path(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user)->post(route('admin.ruc.backups.store'));

        $backup = RucBackup::latest('id')->first();

        // storage_path debe ser relativo al disco "local", NUNCA una ruta
        // absoluta como /var/www/html/storage/app/private/...
        $this->assertStringStartsNotWith('/', $backup->storage_path);
        $this->assertFileExists($backup->absolutePath());
    }
}
