<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El bloque `features` de /auth/login y /auth/me es contrato público: Desktop y
 * Mobile deciden con él si pintan un módulo opcional. Si cambia de forma o de
 * clave, los clientes publicados dejan de enterarse de qué hay disponible.
 */
class ClientFeatureDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_desktop_login_reports_dni_name_search_as_available_when_the_server_has_it_on(): void
    {
        config()->set('dni.name_search.enabled', true);
        config()->set('dni.name_search.providers.dniperu.enabled', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => $this->superAdminEmail(),
            'password' => 'password',
            'application' => 'desktop',
        ])
            ->assertOk()
            ->assertJsonPath('data.features.dni_name_search', true)
            ->assertJsonPath('data.applications', fn (array $apps): bool => in_array('desktop', $apps, true));
    }

    public function test_it_reports_the_module_as_unavailable_when_either_switch_is_off(): void
    {
        $email = $this->superAdminEmail();

        // El interruptor maestro apagado basta, aunque el proveedor esté activo.
        config()->set('dni.name_search.enabled', false);
        config()->set('dni.name_search.providers.dniperu.enabled', true);

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
            'application' => 'desktop',
        ])->assertOk()->assertJsonPath('data.features.dni_name_search', false);

        // Y el del proveedor también, aunque la función esté activa.
        config()->set('dni.name_search.enabled', true);
        config()->set('dni.name_search.providers.dniperu.enabled', false);

        $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
            'application' => 'desktop',
        ])->assertOk()->assertJsonPath('data.features.dni_name_search', false);
    }

    public function test_features_travel_in_me_too_so_a_running_client_notices_a_server_side_change(): void
    {
        config()->set('dni.name_search.enabled', true);
        config()->set('dni.name_search.providers.dniperu.enabled', true);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $this->superAdminEmail(),
            'password' => 'password',
            'application' => 'desktop',
        ])->json('data.access_token');

        self::assertIsString($token);

        // El servidor apaga el módulo mientras la sesión sigue viva.
        config()->set('dni.name_search.providers.dniperu.enabled', false);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.features.dni_name_search', false);
    }

    /**
     * getAttribute() en vez de ->email: el modelo no declara @property y
     * larastan no infiere las columnas, asi que el acceso dinamico seria un
     * error de PHPStan que solo se puede callar metiendolo al baseline.
     */
    private function superAdminEmail(): string
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return (string) $user->getAttribute('email');
    }
}
