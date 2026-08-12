<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Support\RucBackupArchive;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class RucBackupCreateTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * CSRF tiene su propia prueba dedicada (RucBackupCsrfTest, con el flujo
     * real GET -> token -> POST). Aquí, igual que en el resto del proyecto
     * (ver PublicTokenRequestWebTest::postWithCsrf), se planta un token
     * idéntico en la sesión y en el body para poder probar la lógica de
     * negocio sin que el 419 sea la variable bajo prueba.
     */
    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])->post($uri, array_merge($data, ['_token' => $token]));
    }

    public function test_create_backup_requires_authentication(): void
    {
        $response = $this->postWithCsrf(route('admin.ruc.backups.store'));

        $response->assertRedirect(route('login'));
    }

    public function test_create_backup_requires_permission(): void
    {
        $user = User::factory()->create(); // sin rol/permiso

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.store'));

        $response->assertForbidden();
    }

    public function test_create_backup_generates_a_real_file_and_completes(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.store'));

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
        $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.store'));

        $backup = RucBackup::latest('id')->first();
        $this->assertNotNull($backup);
        $this->assertTrue($backup->isChunked());

        $manifest = RucBackupArchive::readManifest($backup->absolutePath());
        RucBackupArchive::assertManifestIsValid($manifest);
        $this->assertSame('ruc_records', $manifest['source_table']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($backup->absolutePath()));
        $this->assertNotFalse($zip->locateName(RucBackupArchive::MANIFEST_ENTRY));
        $zip->close();
    }

    public function test_created_backup_has_valid_checksum(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.store'));

        $backup = RucBackup::latest('id')->first();
        $this->assertNotNull($backup);

        $this->assertSame(hash_file('sha256', $backup->absolutePath()), $backup->checksum_sha256);
    }

    public function test_created_backup_uses_relative_storage_path(): void
    {
        $user = $this->adminUser();
        $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.store'));

        $backup = RucBackup::latest('id')->first();
        $this->assertNotNull($backup);

        // storage_path debe ser relativo al disco "local", NUNCA una ruta
        // absoluta como /var/www/html/storage/app/private/...
        $this->assertStringStartsNotWith('/', $backup->storage_path);
        $this->assertFileExists($backup->absolutePath());
    }
}
