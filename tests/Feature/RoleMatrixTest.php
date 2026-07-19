<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users\Form;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Users\Services\UserSecurityService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_seeders_define_only_three_roles_with_exact_permission_matrix(): void
    {
        $this->assertSame(['editor', 'super-admin', 'viewer'], Role::query()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(['agencies.create', 'agencies.manage_status', 'agencies.update', 'agencies.view', 'dashboard.view'], $this->permissions('editor'));
        $this->assertSame(['agencies.view'], $this->permissions('viewer'));
        $this->assertSame(Permission::query()->count(), count($this->permissions('super-admin')));
    }

    public function test_consulta_only_accesses_agencies_details_and_map(): void
    {
        $user = $this->userFor('viewer');
        $agency = Agency::factory()->create();

        $this->actingAs($user)->get(route('admin.agencies.index'))
            ->assertOk()->assertSee('Agencias')->assertSee('Mapa de agencias')
            ->assertDontSee('Dashboard')->assertDontSee('Nueva agencia')->assertDontSee('Importar')
            ->assertDontSee('Usuarios')->assertDontSee('Design System');
        $this->get(route('admin.agencies.map'))->assertOk();
        $this->get(route('admin.agencies.show', $agency))->assertOk()->assertDontSee('Editar');
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('admin.agencies.create'))->assertForbidden();
        $this->get(route('admin.agencies.edit', $agency))->assertForbidden();
        $this->get(route('admin.agencies.import'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.design-system'))->assertForbidden();
    }

    public function test_editor_accesses_dashboard_and_agency_creation_editing_but_not_sensitive_modules(): void
    {
        $user = $this->userFor('editor');
        $agency = Agency::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->get(route('admin.agencies.index'))->assertOk()->assertSee('Nueva agencia')->assertDontSee('Importar');
        $this->get(route('admin.agencies.map'))->assertOk();
        $this->get(route('admin.agencies.create'))->assertOk();
        $this->get(route('admin.agencies.edit', $agency))->assertOk();
        $this->get(route('admin.agencies.import'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.design-system'))->assertForbidden();
        $this->assertFalse($user->can('delete', $agency));
        $this->assertFalse($user->can('restore', $agency));
    }

    public function test_super_administrator_has_full_access_and_role_selector_is_restricted(): void
    {
        $user = $this->userFor('super-admin');

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->get(route('admin.agencies.index'))->assertOk()->assertSee('Importar');
        $this->get(route('admin.agencies.import'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.design-system'))->assertOk();

        Livewire::test(Form::class)
            ->assertSee('Super Administrador')
            ->assertSee('Consulta')
            ->assertSee('Editor')
            ->assertDontSeeHtml('value="admin"');
    }

    public function test_login_redirects_editor_to_dashboard_and_consulta_to_agencies(): void
    {
        foreach (['editor' => 'dashboard', 'viewer' => 'admin.agencies.index'] as $role => $destination) {
            $user = $this->userFor($role);
            $token = 'csrf-'.$role;
            $this->withSession(['_token' => $token])->post(route('login.store'), [
                '_token' => $token,
                'email' => $user->email,
                'password' => 'Secret12345!',
            ])->assertRedirect(route($destination));
            auth()->logout();
        }
    }

    public function test_role_data_migration_maps_legacy_admin_without_promoting_users(): void
    {
        $admin = Role::query()->create(['slug' => 'admin', 'name' => 'Administrador']);
        $superUser = $this->userFor('super-admin');
        $operator = User::factory()->create();
        $superUser->roles()->attach($admin);
        $operator->roles()->attach($admin);

        $migration = require database_path('migrations/2026_07_19_060000_simplify_system_roles.php');
        $migration->up();

        $this->assertDatabaseMissing('roles', ['slug' => 'admin']);
        $this->assertTrue($operator->fresh()->hasRole('editor'));
        $this->assertFalse($operator->fresh()->hasRole('super-admin'));
        $this->assertTrue($superUser->fresh()->hasRole('super-admin'));
        $this->assertFalse($superUser->fresh()->hasRole('editor'));
    }

    public function test_last_active_super_administrator_cannot_be_degraded(): void
    {
        $target = $this->userFor('super-admin');
        $actor = User::factory()->suspended()->create();
        $actor->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'));

        $this->expectException(AuthorizationException::class);
        app(UserSecurityService::class)->canAssignRoles($actor, $target, ['editor']);
    }

    private function userFor(string $role): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'password' => bcrypt('Secret12345!')]);
        $user->roles()->attach(Role::query()->where('slug', $role)->value('id'));

        return $user;
    }

    /** @return array<int, string> */
    private function permissions(string $role): array
    {
        return Role::query()->where('slug', $role)->firstOrFail()->permissions()->orderBy('slug')->pluck('slug')->all();
    }
}
