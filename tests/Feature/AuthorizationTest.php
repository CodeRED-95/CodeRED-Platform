<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Support\Facades\Blade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_permission_and_role_helpers_work(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Ver agencias',
            'slug' => 'agencies.view',
            'description' => null,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => null,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue($user->hasPermission('agencies.view'));
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasAnyRole(['editor', 'admin']));
        $this->assertTrue($user->hasAllPermissions(['agencies.view']));
    }

    public function test_super_admin_bypasses_policies_via_gate_before(): void
    {
        $role = Role::query()->create([
            'name' => 'Super Administrador',
            'slug' => 'super-admin',
            'description' => null,
            'is_system' => true,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $agency = Agency::factory()->create();

        $this->assertTrue($user->hasRole('super-admin'));
        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Agency::class));
        $this->assertTrue(Gate::forUser($user)->allows('create', Agency::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $agency));
        $this->assertTrue($user->hasPermission('agencies.view'));
    }

    public function test_gate_before_maps_viewany_to_agencies_view_permission(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Ver agencias',
            'slug' => 'agencies.view',
            'description' => null,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => null,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Agency::class));
    }

    public function test_gate_before_allows_database_permissions_without_overriding_user_can(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Ver agencias',
            'slug' => 'agencies.view',
            'description' => null,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => null,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue(Gate::forUser($user)->allows('agencies.view'));
        $this->assertTrue($user->can('agencies.view'));
    }

    public function test_agency_policy_uses_permission_helpers(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Ver agencias',
            'slug' => 'agencies.view',
            'description' => null,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => null,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        $agency = Agency::factory()->create();

        $this->assertTrue(Gate::forUser($user)->allows('view', $agency));
    }

    public function test_blade_can_directive_uses_native_authorization(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Ver agencias',
            'slug' => 'agencies.view',
            'description' => null,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => null,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        $rendered = Blade::render("@can('agencies.view')visible@endcan");

        $this->assertStringContainsString('visible', $rendered);
    }

    public function test_can_middleware_allows_authorized_user(): void
    {
        $permission = Permission::query()->create([
            'name' => 'Ver agencias',
            'slug' => 'agencies.view',
            'description' => null,
        ]);

        $role = Role::query()->create([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => null,
            'is_system' => true,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        Route::middleware('can:agencies.view')->get('/authorization-test', fn () => response('ok'));

        $this->actingAs($user)
            ->get('/authorization-test')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_user_without_permission_is_denied(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/agencies')
            ->assertForbidden();
    }
}
