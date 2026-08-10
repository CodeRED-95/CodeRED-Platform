<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolePermissionSeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_permissions_seeder_can_run_twice_without_duplicate_pivot_rows(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->assertPermissionRolePivotHasNoDuplicates();
        $this->assertRolesAndPermissionsAreUnique();
        $this->assertExpectedPermissionMatrix();
    }

    public function test_roles_and_permissions_seeder_can_run_twice_without_duplicate_pivot_rows(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertPermissionRolePivotHasNoDuplicates();
        $this->assertRolesAndPermissionsAreUnique();
        $this->assertExpectedPermissionMatrix();
    }

    public function test_role_permission_sync_does_not_remove_permissions_from_other_roles(): void
    {
        $customRole = Role::query()->create([
            'slug' => 'custom-auditor',
            'name' => 'Auditor personalizado',
            'is_system' => false,
        ]);
        $customPermission = Permission::query()->create([
            'slug' => 'custom.audit.view',
            'name' => 'Ver auditoría personalizada',
        ]);
        $customRole->permissions()->attach($customPermission->id);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue($customRole->fresh()->permissions()->whereKey($customPermission->id)->exists());
        $this->assertPermissionRolePivotHasNoDuplicates();
    }

    private function assertPermissionRolePivotHasNoDuplicates(): void
    {
        $totalRows = DB::table('permission_role')->count();
        $distinctRows = DB::query()
            ->fromSub(
                DB::table('permission_role')
                    ->select('permission_id', 'role_id')
                    ->distinct(),
                'distinct_permission_role'
            )
            ->count();

        $this->assertSame($distinctRows, $totalRows);
    }

    private function assertRolesAndPermissionsAreUnique(): void
    {
        $this->assertSame(Role::query()->count(), Role::query()->distinct('slug')->count('slug'));
        $this->assertSame(Permission::query()->count(), Permission::query()->distinct('slug')->count('slug'));
    }

    private function assertExpectedPermissionMatrix(): void
    {
        $this->assertSame(['agencies.create', 'agencies.manage_status', 'agencies.map', 'agencies.update', 'agencies.view', 'dashboard.view'], $this->permissionSlugsForRole('editor'));
        $this->assertSame(['agencies.map', 'agencies.view', 'shalom-recordar.sync', 'shalom-recordar.view-own'], $this->permissionSlugsForRole('viewer'));
        $this->assertSame(Permission::query()->count(), Role::query()->where('slug', 'super-admin')->firstOrFail()->permissions()->count());
    }

    /** @return array<int, string> */
    private function permissionSlugsForRole(string $roleSlug): array
    {
        return Role::query()
            ->where('slug', $roleSlug)
            ->firstOrFail()
            ->permissions()
            ->orderBy('slug')
            ->pluck('slug')
            ->all();
    }
}
