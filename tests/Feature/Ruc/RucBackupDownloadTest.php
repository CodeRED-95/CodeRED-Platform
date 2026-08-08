<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Services\RucBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RucBackupDownloadTest extends TestCase
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

    public function test_download_requires_authentication(): void
    {
        $backup = app(RucBackupService::class)->create();

        $response = $this->get(route('admin.ruc.backups.download', $backup));

        $response->assertRedirect(route('login'));
    }

    public function test_download_requires_permission(): void
    {
        $backup = app(RucBackupService::class)->create();
        $user = User::factory()->create(); // sin permiso

        $response = $this->actingAs($user)->get(route('admin.ruc.backups.download', $backup));

        $response->assertForbidden();
    }

    public function test_valid_download_returns_the_file(): void
    {
        $user = $this->adminUser();
        $backup = app(RucBackupService::class)->create();

        $response = $this->actingAs($user)->get(route('admin.ruc.backups.download', $backup));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString($backup->name, $response->headers->get('content-disposition'));
    }

    public function test_missing_file_returns_a_controlled_redirect_not_a_500(): void
    {
        $user = $this->adminUser();
        $backup = app(RucBackupService::class)->create();
        @unlink($backup->absolutePath());

        $response = $this->actingAs($user)->get(route('admin.ruc.backups.download', $backup));

        $response->assertRedirect(route('admin.ruc.backups'));
        $response->assertSessionHas('error');
    }

    public function test_incomplete_backup_cannot_be_downloaded(): void
    {
        $user = $this->adminUser();
        $backup = RucBackup::create([
            'name' => 'pending.dump',
            'storage_path' => 'backups/ruc/pending.dump',
            'status' => RucBackup::STATUS_CREATING,
        ]);

        $response = $this->actingAs($user)->get(route('admin.ruc.backups.download', $backup));

        $response->assertRedirect(route('admin.ruc.backups'));
    }
}
