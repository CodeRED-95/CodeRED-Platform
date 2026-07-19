<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Show as AgencyShow;
use App\Livewire\Admin\Users\Show as UserShow;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyChangeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_record_actor_differences_and_never_store_password_hashes(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $target = User::factory()->create([
            'name' => 'Nombre inicial',
            'password' => 'ClaveInicial123!',
        ]);

        $this->assertSame($actor->id, $target->created_by);
        $created = ActivityLog::query()->where('auditable_id', $target->id)->where('action', 'created')->firstOrFail();
        $this->assertSame($actor->id, $created->user_id);
        $this->assertSame('127.0.0.1', $created->ip_address);
        $this->assertArrayNotHasKey('password', $created->new_values);
        $this->assertContains('credentials', $created->changed_fields);

        $target->update(['name' => 'Nombre actualizado', 'password' => 'OtraClave123!']);
        $target->refresh();

        $updated = ActivityLog::query()->where('auditable_id', $target->id)->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $target->updated_by);
        $this->assertSame('Nombre inicial', $updated->old_values['name']);
        $this->assertSame('Nombre actualizado', $updated->new_values['name']);
        $this->assertContains('credentials', $updated->changed_fields);
        $this->assertArrayNotHasKey('password', $updated->old_values);
        $this->assertArrayNotHasKey('password', $updated->new_values);
    }

    public function test_agency_changes_record_actor_author_and_changed_fields(): void
    {
        $actor = User::factory()->create();
        $this->actingAs($actor);

        $agency = Agency::factory()->create(['name' => 'Agencia inicial']);
        $agency->update(['name' => 'Agencia actualizada']);
        $agency->refresh();

        $log = AgencyChangeLog::query()->where('agency_id', $agency->id)->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $agency->created_by);
        $this->assertSame($actor->id, $agency->updated_by);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertContains('name', $log->changed_fields);
        $this->assertSame('127.0.0.1', $log->ip_address);
    }

    public function test_user_activity_is_only_rendered_with_sensitive_activity_permission(): void
    {
        $target = User::factory()->create(['name' => 'Cuenta auditada']);
        $log = ActivityLog::query()->where('auditable_id', $target->id)->latest('id')->firstOrFail();
        $viewer = $this->userWithPermissions(['users.view']);

        Livewire::actingAs($viewer)->test(UserShow::class, ['user' => $target])
            ->assertViewHas('canViewActivity', false)
            ->assertDontSee('Historial de auditoría')
            ->assertDontSee((string) $log->ip_address);

        $auditor = $this->userWithPermissions(['users.view', 'users.view_activity']);
        Livewire::actingAs($auditor)->test(UserShow::class, ['user' => $target])
            ->assertViewHas('canViewActivity', true)
            ->assertSee('Historial de auditoría')
            ->assertSee('Creación');
    }

    public function test_agency_history_is_only_rendered_with_history_permission(): void
    {
        $agency = Agency::factory()->create();
        $viewer = $this->userWithPermissions(['agencies.view']);

        Livewire::actingAs($viewer)->test(AgencyShow::class, ['agency' => $agency])
            ->assertViewHas('canViewHistory', false)
            ->assertDontSee('Historial de auditoría');

        $auditor = $this->userWithPermissions(['agencies.view', 'agencies.view_history']);
        Livewire::actingAs($auditor)->test(AgencyShow::class, ['agency' => $agency])
            ->assertViewHas('canViewHistory', true)
            ->assertSee('Historial de auditoría')
            ->assertSee('Creación');
    }

    private function userWithPermissions(array $slugs): User
    {
        $permissions = collect($slugs)->map(fn (string $slug): Permission => Permission::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => $slug],
        ));
        $role = Role::query()->create([
            'name' => 'Rol '.uniqid(),
            'slug' => 'role-'.uniqid(),
        ]);
        $role->permissions()->sync($permissions->pluck('id'));
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
