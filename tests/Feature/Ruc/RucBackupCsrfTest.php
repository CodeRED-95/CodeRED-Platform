<?php

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Services\RucBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Prueba dedicada al problema original: 419 al crear/importar un backup.
 *
 * A diferencia del resto de tests de este módulo (que desactivan CSRF para
 * enfocarse en su propia lógica), aquí la protección se deja activa a
 * propósito: es justo lo que se está verificando. Sigue el flujo real de un
 * navegador: GET para obtener sesión + token, POST con ese token en la
 * misma sesión.
 */
class RucBackupCsrfTest extends TestCase
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

    private function extractCsrfToken(string $html): ?string
    {
        return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) ? $m[1] : null;
    }

    public function test_create_backup_does_not_return_419_within_the_same_session(): void
    {
        $user = $this->adminUser();

        $getResponse = $this->actingAs($user)->get(route('admin.ruc.backups'));
        $getResponse->assertOk();

        $token = $this->extractCsrfToken($getResponse->getContent());
        $this->assertNotEmpty($token, 'No se pudo extraer un token CSRF de la página');

        $postResponse = $this->post(route('admin.ruc.backups.store'), ['_token' => $token]);

        $postResponse->assertStatus(302);
        $postResponse->assertRedirect(route('admin.ruc.backups'));
        $postResponse->assertSessionHas('success');
    }

    public function test_import_backup_does_not_return_419_within_the_same_session(): void
    {
        $user = $this->adminUser();

        $getResponse = $this->actingAs($user)->get(route('admin.ruc.backups'));
        $token = $this->extractCsrfToken($getResponse->getContent());
        $this->assertNotEmpty($token);

        $backup = app(RucBackupService::class)->create();
        $file = new UploadedFile($backup->absolutePath(), 'reupload.dump', null, null, true);

        $postResponse = $this->post(route('admin.ruc.backups.import'), [
            '_token' => $token,
            'backup' => $file,
        ]);

        $postResponse->assertStatus(302);
        $postResponse->assertSessionHas('success');
    }

    public function test_invalid_csrf_token_is_still_correctly_rejected(): void
    {
        // Confirma que la corrección NO deshabilitó la protección CSRF.
        $user = $this->adminUser();

        $response = $this->actingAs($user)->post(route('admin.ruc.backups.store'), ['_token' => 'token-invalido']);

        $response->assertStatus(419);
    }

    public function test_missing_csrf_token_is_rejected(): void
    {
        $user = $this->adminUser();

        $response = $this->actingAs($user)->post(route('admin.ruc.backups.store'), []);

        $response->assertStatus(419);
    }
}
