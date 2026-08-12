<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucChunkedBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class RucBackupImportTest extends TestCase
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

    /** Ver RucBackupCreateTest::postWithCsrf() / PublicTokenRequestWebTest. */
    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])->post($uri, array_merge($data, ['_token' => $token]));
    }

    /** Backup válido de ruc_records, generado por la ruta de testing aislada. */
    private function validDumpFile(string $name = 'valid.dump'): UploadedFile
    {
        $backup = app(RucChunkedBackupService::class)->create();

        return new UploadedFile($backup->absolutePath(), $name, null, null, true);
    }

    public function test_import_requires_authentication(): void
    {
        $response = $this->postWithCsrf(route('admin.ruc.backups.import'));

        $response->assertRedirect(route('login'));
    }

    public function test_traditional_multipart_post_import_works(): void
    {
        $user = $this->adminUser();
        $file = $this->validDumpFile();

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), [
            'backup' => $file,
        ]);

        // Redirect tradicional, NUNCA una respuesta JSON.
        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('ruc_backups', ['status' => RucBackup::STATUS_COMPLETED]);
    }

    public function test_valid_dump_is_accepted(): void
    {
        $user = $this->adminUser();
        $file = $this->validDumpFile();

        $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), ['backup' => $file]);

        $imported = RucBackup::latest('id')->first();
        $this->assertTrue($imported->fileExists());
        $this->assertNotNull($imported->checksum_sha256);
    }

    public function test_invalid_file_is_rejected(): void
    {
        $user = $this->adminUser();
        $file = UploadedFile::fake()->createWithContent('fake.dump', 'esto no es un dump real');

        $countBefore = RucBackup::count();

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), ['backup' => $file]);

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('error');
        $this->assertSame($countBefore, RucBackup::count());
    }

    public function test_dump_of_another_table_is_rejected(): void
    {
        $user = $this->adminUser();
        $backup = app(RucChunkedBackupService::class)->create();
        $tmpPath = tempnam(sys_get_temp_dir(), 'other').'.rucbackup';
        copy($backup->absolutePath(), $tmpPath);

        $zip = new ZipArchive;
        $zip->open($tmpPath);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['source_table'] = 'users';
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $file = new UploadedFile($tmpPath, 'other_table.dump', null, null, true);
        $countBefore = RucBackup::count();

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), ['backup' => $file]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('ruc_records', session('error'));
        $this->assertSame($countBefore, RucBackup::count());

        @unlink($tmpPath);
    }

    public function test_checksum_is_stored_for_imported_file(): void
    {
        $user = $this->adminUser();
        $file = $this->validDumpFile();
        $expectedChecksum = hash_file('sha256', $file->getRealPath());

        $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), ['backup' => $file]);

        $this->assertDatabaseHas('ruc_backups', ['checksum_sha256' => $expectedChecksum]);
    }

    public function test_file_is_stored_on_disk(): void
    {
        $user = $this->adminUser();
        $file = $this->validDumpFile();

        $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), ['backup' => $file]);

        $imported = RucBackup::latest('id')->first();
        $this->assertFileExists($imported->absolutePath());
    }

    public function test_legacy_gz_extension_is_accepted_when_content_is_valid(): void
    {
        $user = $this->adminUser();
        $file = $this->validDumpFile('legacy.sql.gz');

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), ['backup' => $file]);

        $response->assertSessionHas('success');
    }

    public function test_invalid_extension_is_rejected_before_processing(): void
    {
        $user = $this->adminUser();
        $file = UploadedFile::fake()->create('not-a-backup.txt', 10);

        $response = $this->actingAs($user)->postWithCsrf(route('admin.ruc.backups.import'), ['backup' => $file]);

        $response->assertSessionHasErrors('backup');
    }
}
