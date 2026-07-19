<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Index as AgenciesIndex;
use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SoftDeleteManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_restore_and_force_delete_agency(): void
    {
        $actor = $this->superAdmin();
        $agency = Agency::factory()->create();

        Livewire::actingAs($actor)->test(AgenciesIndex::class)
            ->call('deleteAgency', $agency->id)
            ->assertDispatched('toast')
            ->set('withTrashed', 'only')
            ->assertSee($agency->name)
            ->call('restoreAgency', $agency->id)
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted($agency);

        $agency->delete();
        Livewire::actingAs($actor)->test(AgenciesIndex::class)
            ->set('withTrashed', 'only')
            ->call('forceDeleteAgency', $agency->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('agencies', ['id' => $agency->id]);
    }

    public function test_super_admin_can_delete_restore_and_force_delete_user(): void
    {
        $actor = $this->superAdmin();
        $target = User::factory()->create();

        Livewire::actingAs($actor)->test(UsersIndex::class)
            ->call('deleteUser', $target->id)
            ->assertDispatched('toast')
            ->set('trash', 'only')
            ->assertSee($target->email)
            ->call('restoreUser', $target->id)
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted($target);

        $target->delete();
        Livewire::actingAs($actor)->test(UsersIndex::class)
            ->set('trash', 'only')
            ->call('forceDeleteUser', $target->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_viewer_cannot_execute_destructive_actions(): void
    {
        $agencyView = Permission::query()->create(['slug' => 'agencies.view', 'name' => 'Ver agencias']);
        $usersView = Permission::query()->create(['slug' => 'users.view', 'name' => 'Ver usuarios']);
        $role = Role::query()->create(['slug' => 'viewer', 'name' => 'Consulta']);
        $role->permissions()->sync([$agencyView->id, $usersView->id]);
        $actor = User::factory()->create();
        $actor->roles()->attach($role);
        $agency = Agency::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($actor)->test(AgenciesIndex::class)
            ->call('deleteAgency', $agency->id)
            ->assertForbidden();

        Livewire::actingAs($actor)->test(UsersIndex::class)
            ->call('deleteUser', $target->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted($agency);
        $this->assertNotSoftDeleted($target);
    }

    public function test_super_admin_cannot_delete_own_account(): void
    {
        $actor = $this->superAdmin();

        Livewire::actingAs($actor)->test(UsersIndex::class)
            ->call('deleteUser', $actor->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted($actor);
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrador', 'is_system' => true],
        );
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
