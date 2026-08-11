<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\ApiDocumentation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Documentación de la API (/docs). Pruebas básicas de carga, secciones,
 * render de endpoints y limpieza de la vista antigua. No dispara integraciones.
 */
class ApiDocumentationPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function user(): User
    {
        $role = Role::query()->where('slug', 'viewer')->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_docs_route_loads_for_an_authenticated_user(): void
    {
        $this->actingAs($this->user())
            ->get('/docs')
            ->assertOk()
            ->assertSee('Documentación de la API');
    }

    public function test_docs_render_all_real_module_sections(): void
    {
        Livewire::actingAs($this->user())
            ->test(ApiDocumentation::class)
            ->assertSee('Introducción')
            ->assertSee('Autenticación')
            ->assertSee('Tokens')
            ->assertSee('Agencias')
            ->assertSee('RUC')
            ->assertSee('DNI')
            ->assertSee('Shalom Recordar')
            ->assertSee('Integraciones')
            ->assertSee('Errores comunes');
    }

    public function test_docs_render_real_endpoints_with_method_and_ability(): void
    {
        Livewire::actingAs($this->user())
            ->test(ApiDocumentation::class)
            ->assertSee('/api/v1/agencies')
            ->assertSee('agencies:read')
            ->assertSee('/api/v1/ruc/{ruc}')
            ->assertSee('/api/v1/dni/{dni}')
            ->assertSee('/api/v1/shalom-recordar/sync')
            ->assertSee('GET')
            ->assertSee('POST');
    }

    public function test_docs_include_copy_buttons_and_code_examples(): void
    {
        Livewire::actingAs($this->user())
            ->test(ApiDocumentation::class)
            ->assertSee('Copiar ruta')
            ->assertSee('Ejemplo de request')
            ->assertSee('Ejemplo de response')
            ->assertSee('curl -s', false);
    }

    public function test_docs_have_a_sectioned_navigation(): void
    {
        Livewire::actingAs($this->user())
            ->test(ApiDocumentation::class)
            ->assertSeeHtml('Índice de la documentación')      // nav móvil
            ->assertSeeHtml('doc-agencias')                     // ancla de sección
            ->assertSeeHtml('aria-label="Secciones de la documentación"');
    }

    public function test_docs_no_longer_show_the_openapi_or_interactive_blocks(): void
    {
        Livewire::actingAs($this->user())
            ->test(ApiDocumentation::class)
            ->assertDontSee('codered-swagger-ui', false);
    }

    public function test_common_errors_section_lists_the_status_codes(): void
    {
        Livewire::actingAs($this->user())
            ->test(ApiDocumentation::class)
            ->assertSee('401')
            ->assertSee('403')
            ->assertSee('422')
            ->assertSee('429')
            ->assertSee('No autenticado')
            ->assertSee('Sin permiso');
    }

    public function test_docs_require_authentication_when_not_public(): void
    {
        // Por defecto la documentación no es pública: un invitado se redirige al
        // login (lo aplica EnsureApiDocumentationAccess) y no ve el contenido.
        config()->set('api.docs_public', false);

        $this->get('/docs')
            ->assertRedirect(route('login'));
    }
}
