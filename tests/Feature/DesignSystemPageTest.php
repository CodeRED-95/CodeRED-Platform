<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignSystemPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate([
            'slug' => 'super-admin',
        ], [
            'name' => 'Super Administrador',
            'description' => null,
            'is_system' => true,
        ]);

        Permission::query()->firstOrCreate([
            'slug' => 'agencies.view',
        ], [
            'name' => 'Ver agencias',
            'description' => null,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_design_system_page_uses_admin_layout_for_authorized_user(): void
    {
        $user = $this->makeSuperAdmin();

        $this->actingAs($user)
            ->get('/admin/design-system')
            ->assertOk()
            ->assertSee('CodeRED Design System')
            ->assertSee('CodeRED Platform')
            ->assertSee('Dashboard');
    }

    public function test_design_system_page_is_forbidden_for_regular_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/design-system')
            ->assertForbidden();
    }
}
