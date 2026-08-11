<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyAuthorizationPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function makeRoleWithPermissions(string $slug, array $permissions): Role
    {
        $roleSlug = trim(strtolower($slug));
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            [
                'name' => ucfirst(str_replace('-', ' ', $roleSlug)),
                'description' => null,
                'is_system' => true,
            ]
        );

        $permissionSlugs = collect($permissions)
            ->map(fn (string $permission): string => trim(strtolower($permission)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $ids = Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id')->all();

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
