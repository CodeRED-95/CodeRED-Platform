<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RucBackupPageTest extends TestCase
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

    public function test_page_returns_200(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertOk();
    }

    public function test_page_requires_authentication(): void
    {
        $response = $this->get(route('admin.ruc.backups'));

        $response->assertRedirect(route('login'));
    }

    public function test_page_shows_create_backup_button(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertSee('Crear Backup');
    }

    public function test_page_shows_import_backup_form(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertSee('Importar Backup');
        $response->assertSee('enctype="multipart/form-data"', false);
    }

    /**
     * Regresión directa del sistema anterior: @match/@endmatch nunca fue
     * una directiva real de Blade y se renderizaba como texto literal.
     */
    public function test_page_never_contains_the_literal_match_directive(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertDontSee('@match', false);
        $response->assertDontSee('@endmatch', false);
    }

    public function test_page_uses_traditional_forms_not_fetch(): void
    {
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));
        $content = $response->getContent();

        $this->assertStringNotContainsString('fetch(', $content);
        $this->assertStringNotContainsString("addEventListener('submit'", $content);
        $this->assertStringNotContainsString('preventDefault', $content);
    }

    public function test_page_renders_full_layout_shell(): void
    {
        // Regresión: <x-layouts.app> resuelve a un stub distinto
        // (resources/views/components/layouts/app.blade.php) que descarta
        // el sidebar/toast del layout real.
        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertSee('id="global-toast-region"', false);
    }

    public function test_page_lists_existing_backups(): void
    {
        RucBackup::create([
            'name' => 'ruc_backup_visible_test.dump',
            'storage_path' => 'backups/ruc/ruc_backup_visible_test.dump',
            'status' => RucBackup::STATUS_COMPLETED,
            'total_records' => 42,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertSee('ruc_backup_visible_test.dump');
        $response->assertSee('42');
    }
}
