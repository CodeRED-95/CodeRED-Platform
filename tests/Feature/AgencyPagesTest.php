<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyPagesTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAgencyManager(): User
    {
        $permissions = collect([
            ['slug' => 'agencies.view', 'name' => 'Ver agencias'],
            ['slug' => 'agencies.create', 'name' => 'Crear agencias'],
            ['slug' => 'agencies.update', 'name' => 'Editar agencias'],
            ['slug' => 'agencies.import', 'name' => 'Importar agencias'],
            ['slug' => 'agencies.view_history', 'name' => 'Ver historial'],
        ])->map(function (array $permission): Permission {
            return Permission::query()->create([
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'description' => null,
            ]);
        });

        $role = Role::query()->create([
            'name' => 'Administrador',
            'slug' => 'admin',
            'description' => null,
            'is_system' => true,
        ]);

        $role->permissions()->sync($permissions->pluck('id')->all());

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_agencies_page_loads_for_authorized_user(): void
    {
        Agency::factory()->create(['status' => AgencyStatus::Active, 'is_operations_center' => true]);

        $this->actingAs($this->actingAsAgencyManager())
            ->get('/admin/agencies')
            ->assertOk()
            ->assertSee('Agencias Shalom')
            ->assertSee('Centro de Operaciones')
            ->assertSee('Nueva agencia');
    }

    public function test_agency_create_form_loads(): void
    {
        $this->actingAs($this->actingAsAgencyManager())
            ->get('/admin/agencies/create')
            ->assertOk()
            ->assertSee('Nueva agencia')
            ->assertSee('Centro de Operaciones')
            ->assertSee('Traslado');
    }

    public function test_agency_form_renders_livewire_submit_and_submit_button(): void
    {
        $this->actingAs($this->actingAsAgencyManager());

        Livewire::test(\App\Livewire\Admin\Agencies\Form::class)
            ->assertSeeHtml('wire:submit.prevent="save"')
            ->assertSeeHtml('<button type="submit"');
    }

    public function test_admin_agency_detail_loads(): void
    {
        $agency = Agency::factory()->create();

        $this->actingAs($this->actingAsAgencyManager())
            ->get('/admin/agencies/'.$agency->id)
            ->assertOk()
            ->assertSee($agency->name)
            ->assertSee($agency->code);
    }

    public function test_public_agencies_page_loads(): void
    {
        Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);

        $this->get('/agencies')
            ->assertOk()
            ->assertSee('Agencias Shalom')
            ->assertSee('Ver detalle');
    }

    public function test_public_agency_detail_loads(): void
    {
        $agency = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);

        $this->get('/agencies/'.$agency->code)
            ->assertOk()
            ->assertSee($agency->name)
            ->assertSee($agency->code);
    }

    public function test_import_page_loads_for_authorized_user(): void
    {
        $this->actingAs($this->actingAsAgencyManager())
            ->get('/admin/agencies/import')
            ->assertOk()
            ->assertSee('Importar agencias');
    }
}
