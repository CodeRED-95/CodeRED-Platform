<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyAuthorizationPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoleWithPermissions(string $slug, array $permissions): Role
    {
        $role = Role::query()->create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'description' => null,
            'is_system' => true,
        ]);

        $ids = collect($permissions)->map(function (string $permission): int {
            return Permission::query()->updateOrCreate(
                ['slug' => $permission],
                ['name' => $permission, 'description' => null]
            )->id;
        })->all();

        $role->permissions()->syncWithoutDetaching($ids);

        return $role;
    }

    public function test_super_admin_accesses_agencies_listing(): void
    {
        $role = $this->makeRoleWithPermissions('super-admin', [
            'agencies.view',
            'agencies.create',
            'agencies.import',
            'agencies.view_history',
            'agencies.manage_status',
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get('/admin/agencies')
            ->assertOk()
            ->assertSee('Agencias Shalom');
    }

    public function test_admin_with_view_permission_accesses_agencies_listing(): void
    {
        $role = $this->makeRoleWithPermissions('admin', ['agencies.view']);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get('/admin/agencies')
            ->assertOk();
    }

    public function test_user_without_view_permission_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/agencies')
            ->assertForbidden();
    }

    public function test_super_admin_can_open_create_page(): void
    {
        $role = $this->makeRoleWithPermissions('super-admin', ['agencies.create']);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get('/admin/agencies/create')
            ->assertOk();
    }

    public function test_user_without_create_permission_cannot_open_create_page(): void
    {
        $role = $this->makeRoleWithPermissions('admin', ['agencies.view']);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user)
            ->get('/admin/agencies/create')
            ->assertForbidden();
    }
}
