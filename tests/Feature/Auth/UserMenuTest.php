<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menú de usuario del panel web y cierre de sesión real.
 */
class UserMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $slug): User
    {
        $role = Role::query()->where('slug', $slug)->firstOrFail();
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_menu_shows_name_email_role_profile_and_logout(): void
    {
        $user = $this->userWithRole('viewer');

        $response = $this->actingAs($user)->get(route('admin.agencies.index'));

        $response->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email)
            ->assertSee('Consulta')          // etiqueta del rol viewer
            ->assertSee('Perfil')
            ->assertSee('Cerrar sesión')
            ->assertSee('aria-haspopup="menu"', false);
    }

    public function test_viewer_menu_offers_its_own_syncs(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->get(route('admin.agencies.index'))
            ->assertOk()
            ->assertSee('Mis sincronizaciones')
            ->assertSee(route('admin.shalom-recordar.users.show', $user), false);
    }

    public function test_super_admin_menu_shows_super_administrator_role(): void
    {
        $user = $this->userWithRole('super-admin');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Super Administrador')
            ->assertSee('Cerrar sesión');
    }

    public function test_logout_destroys_the_session(): void
    {
        $user = $this->userWithRole('viewer');

        $this->actingAs($user)->get(route('admin.agencies.index'))->assertOk();
        $this->assertAuthenticatedAs($user);

        // El menú envía POST al logout real de Laravel (con CSRF).
        $token = 'csrf-logout';
        $this->withSession(['_token' => $token])
            ->post(route('logout'), ['_token' => $token])
            ->assertRedirect();

        $this->assertGuest();
    }
}
