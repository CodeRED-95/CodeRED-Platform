<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupUpload;
use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultipartCancelTest extends TestCase
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

    private function deleteWithCsrf(string $uri)
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])->json('DELETE', $uri, ['_token' => $token]);
    }

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])->post($uri, array_merge($data, ['_token' => $token]));
    }

    private function makeSession(User $user): RucBackupUpload
    {
        $manifest = [
            'format_version' => 1,
            'tool' => 'ruc-tools',
            'tool_version' => '2.3.0',
            'backup_type' => 'ruc_records',
            'created_at' => now()->toIso8601String(),
            'original_filename' => 'ruc_backup_cancel_test.dump',
            'total_records' => 1,
            'total_size_bytes' => 100,
            'part_size_bytes' => 100,
            'total_parts' => 1,
            'sha256' => hash('sha256', str_repeat('A', 100)),
            'parts' => [
                ['index' => 1, 'filename' => 'ruc_backup_cancel_test.dump.part0001', 'size_bytes' => 100, 'sha256' => hash('sha256', str_repeat('A', 100))],
            ],
        ];

        return app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);
    }

    public function test_cancel_marks_upload_as_cancelled_and_deletes_temp_directory(): void
    {
        $user = $this->adminUser();
        $upload = $this->makeSession($user);
        $tempDir = $upload->temporary_directory;
        $this->assertTrue(Storage::disk('local')->exists($tempDir));

        $response = $this->actingAs($user)->deleteWithCsrf(route('admin.ruc.backups.multipart.destroy', ['upload' => $upload->uuid]));

        $response->assertOk();
        $this->assertSame(RucBackupUpload::STATUS_CANCELLED, $upload->fresh()->status);
        $this->assertFalse(Storage::disk('local')->exists($tempDir));
    }

    public function test_cannot_upload_parts_after_cancel(): void
    {
        $user = $this->adminUser();
        $upload = $this->makeSession($user);
        $this->actingAs($user)->deleteWithCsrf(route('admin.ruc.backups.multipart.destroy', ['upload' => $upload->uuid]));

        $response = $this->actingAs($user)->postWithCsrf(
            route('admin.ruc.backups.multipart.upload-part', ['upload' => $upload->uuid, 'index' => 1]),
            ['part' => UploadedFile::fake()->createWithContent('ruc_backup_cancel_test.dump.part0001', str_repeat('A', 100))]
        );

        $response->assertStatus(422);
        $this->assertStringContainsString('ya no acepta partes', $response->json('message'));
    }

    public function test_cancel_never_deletes_completed_backups(): void
    {
        $user = $this->adminUser();
        $upload = $this->makeSession($user);
        $upload->update(['status' => RucBackupUpload::STATUS_COMPLETED]);
        $backupCountBefore = RucBackup::count();

        $response = $this->actingAs($user)->deleteWithCsrf(route('admin.ruc.backups.multipart.destroy', ['upload' => $upload->uuid]));

        $response->assertOk();
        $this->assertSame($backupCountBefore, RucBackup::count());
        $this->assertSame(RucBackupUpload::STATUS_COMPLETED, $upload->fresh()->status, 'cancel() no debe tocar una sesión ya completada.');
    }

    public function test_cancel_rejects_another_users_session(): void
    {
        $owner = $this->adminUser();
        $intruder = $this->adminUser();
        $upload = $this->makeSession($owner);

        $response = $this->actingAs($intruder)->deleteWithCsrf(route('admin.ruc.backups.multipart.destroy', ['upload' => $upload->uuid]));

        $response->assertStatus(403);
        $this->assertSame(RucBackupUpload::STATUS_PENDING, $upload->fresh()->status);
    }
}
