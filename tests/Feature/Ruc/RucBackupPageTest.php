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
        // Con la tabla vacía, x-ui.confirm-dialog (única fuente real de
        // "requestSubmit" en la página) nunca se renderiza — se necesita al
        // menos un backup completado para que la aserción pruebe algo real.
        RucBackup::create([
            'name' => 'ruc_backup_forms_test.dump',
            'storage_path' => 'backups/ruc/ruc_backup_forms_test.dump',
            'status' => RucBackup::STATUS_COMPLETED,
            'total_records' => 1,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));
        $content = $response->getContent();

        $this->assertStringNotContainsString('fetch(', $content);
        $this->assertStringNotContainsString("addEventListener('submit'", $content);
        $this->assertStringNotContainsString('onsubmit=', $content);
        // preventDefault() por sí solo ya no es una señal fiable de "JS
        // interceptando el submit": x-ui.confirm-dialog lo usa legítimamente
        // para el focus-trap del Tab dentro del modal, y x-ui.file-dropzone
        // para el drag & drop — ninguno de los dos toca el evento submit del
        // <form>. Lo que sí debe seguir siendo cierto es que la confirmación
        // termina en form.requestSubmit(), nunca en un fetch/XHR.
        $this->assertStringContainsString('requestSubmit', $content);
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

    /**
     * Regresión directa del bug reportado: restaurar/eliminar mostraban un
     * confirm() nativo del navegador ("localhost:8090 dice..."). Ahora deben
     * usar exclusivamente x-ui.confirm-dialog.
     */
    public function test_page_does_not_use_native_browser_confirm(): void
    {
        RucBackup::create([
            'name' => 'ruc_backup_confirm_test.dump',
            'storage_path' => 'backups/ruc/ruc_backup_confirm_test.dump',
            'status' => RucBackup::STATUS_COMPLETED,
            'total_records' => 10,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));
        $content = $response->getContent();

        $this->assertStringNotContainsString('onclick="return confirm(', $content);
        $this->assertStringNotContainsString('window.confirm(', $content);
        $this->assertStringNotContainsString('window.alert(', $content);
    }

    public function test_page_uses_confirm_dialog_for_restore_and_delete(): void
    {
        $backup = RucBackup::create([
            'name' => 'ruc_backup_dialog_test.dump',
            'storage_path' => 'backups/ruc/ruc_backup_dialog_test.dump',
            'status' => RucBackup::STATUS_COMPLETED,
            'total_records' => 10,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        // x-ui.confirm-dialog no pone id="..." en su nodo raíz: el prop
        // "id" es prefijo de los sub-nodos accesibles (aria-labelledby/
        // aria-describedby). El <form> real sí tiene su id literal, escrito
        // directamente en la vista RUC (fuera del componente).
        $response->assertSee('role="dialog"', false);
        $response->assertSee('aria-labelledby="restore-dialog-'.$backup->id.'-title"', false);
        $response->assertSee('id="restore-form-'.$backup->id.'"', false);
        $response->assertSee('aria-labelledby="delete-dialog-'.$backup->id.'-title"', false);
        $response->assertSee('id="delete-form-'.$backup->id.'"', false);
        $response->assertSee('Safety Backup', false);
    }

    public function test_page_shows_current_record_count_and_last_backup(): void
    {
        RucBackup::create([
            'name' => 'ruc_backup_stats_test.dump',
            'storage_path' => 'backups/ruc/ruc_backup_stats_test.dump',
            'status' => RucBackup::STATUS_COMPLETED,
            'total_records' => 5,
        ]);

        $response = $this->actingAs($this->adminUser())->get(route('admin.ruc.backups'));

        $response->assertSee('Registros actuales');
        $response->assertSee('Último backup');
    }
}
