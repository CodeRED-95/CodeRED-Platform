<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackupUpload;
use App\Modules\Ruc\Services\RucBackupMultipartUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MultipartCleanupTest extends TestCase
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

    private function makeSession(User $user, string $status, \DateTimeInterface $expiresAt): RucBackupUpload
    {
        $manifest = [
            'format_version' => 1,
            'tool' => 'ruc-tools',
            'tool_version' => '2.3.0',
            'backup_type' => 'ruc_records',
            'created_at' => now()->toIso8601String(),
            'original_filename' => 'ruc_backup_cleanup_test_'.uniqid().'.dump',
            'total_records' => 1,
            'total_size_bytes' => 100,
            'part_size_bytes' => 100,
            'total_parts' => 1,
            'sha256' => hash('sha256', 'x'),
            'parts' => [
                ['index' => 1, 'filename' => 'part.dump.part0001', 'size_bytes' => 100, 'sha256' => hash('sha256', 'x')],
            ],
        ];

        $upload = app(RucBackupMultipartUploadService::class)->createSession($manifest, $user);
        $upload->update(['status' => $status, 'expires_at' => $expiresAt]);

        return $upload;
    }

    public function test_expired_incomplete_upload_is_cancelled_and_cleaned(): void
    {
        $user = $this->adminUser();
        $upload = $this->makeSession($user, RucBackupUpload::STATUS_UPLOADING, now()->subHours(25));
        $tempDir = $upload->temporary_directory;

        $cleaned = app(RucBackupMultipartUploadService::class)->cleanupExpired();

        $this->assertSame(1, $cleaned);
        $this->assertSame(RucBackupUpload::STATUS_CANCELLED, $upload->fresh()->status);
        $this->assertFalse(Storage::disk('local')->exists($tempDir));
    }

    public function test_non_expired_upload_is_left_untouched(): void
    {
        $user = $this->adminUser();
        $upload = $this->makeSession($user, RucBackupUpload::STATUS_UPLOADING, now()->addHours(1));

        $cleaned = app(RucBackupMultipartUploadService::class)->cleanupExpired();

        $this->assertSame(0, $cleaned);
        $this->assertSame(RucBackupUpload::STATUS_UPLOADING, $upload->fresh()->status);
    }

    public function test_completed_upload_past_expiry_is_never_touched(): void
    {
        $user = $this->adminUser();
        $upload = $this->makeSession($user, RucBackupUpload::STATUS_COMPLETED, now()->subHours(48));

        $cleaned = app(RucBackupMultipartUploadService::class)->cleanupExpired();

        $this->assertSame(0, $cleaned);
        $this->assertSame(RucBackupUpload::STATUS_COMPLETED, $upload->fresh()->status);
    }

    public function test_artisan_command_runs_cleanup(): void
    {
        $user = $this->adminUser();
        $this->makeSession($user, RucBackupUpload::STATUS_PENDING, now()->subHours(30));

        $this->artisan('ruc:cleanup-backup-uploads')
            ->expectsOutputToContain('Sesiones de subida expiradas limpiadas: 1')
            ->assertExitCode(0);
    }
}
