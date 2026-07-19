<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Form;
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

        Livewire::test(Form::class)
            ->assertSeeHtml('wire:submit.prevent="save"')
            ->assertSeeHtml('<button type="submit"');
    }

    public function test_agency_status_selector_is_dark_accessible_and_uses_real_values(): void
    {
        $this->actingAs($this->actingAsAgencyManager());

        Livewire::test(Form::class)
            ->assertSet('status', AgencyStatus::UnderReview->value)
            ->assertSeeHtml('id="agency-status"')
            ->assertSeeHtml('role="combobox"')
            ->assertSeeHtml('aria-haspopup="listbox"')
            ->assertSeeHtml('aria-controls="agency-status-listbox"')
            ->assertSeeHtml('role="listbox"')
            ->assertSeeHtml('role="option"')
            ->assertSeeHtml('x-on:keydown.arrow-down.prevent="move(1)"')
            ->assertSeeHtml('x-on:keydown.arrow-up.prevent="move(-1)"')
            ->assertSeeHtml('x-on:keydown.escape.stop="closeList(); $refs.trigger.focus()"')
            ->assertSeeHtml('bg-[color:var(--color-background-elevated)]')
            ->assertSeeHtml("'bg-blue-600 text-white'")
            ->assertSeeHtml('wire:model="status"')
            ->assertSee('Activa')
            ->assertSee('Inactiva')
            ->assertSee('Cerrada temporalmente')
            ->assertSee('En revisión')
            ->assertSee('Trasladada')
            ->assertDontSeeHtml('<select wire:model="status"');
    }

    public function test_agency_status_selector_loads_stored_value_when_editing(): void
    {
        $agency = Agency::factory()->create(['status' => AgencyStatus::TemporarilyClosed]);

        $this->actingAs($this->actingAsAgencyManager());

        Livewire::test(Form::class, ['agency' => $agency])
            ->assertSet('status', AgencyStatus::TemporarilyClosed->value)
            ->assertSeeHtml('value="temporarily_closed"');
    }

    public function test_agency_destination_selector_renders_single_combobox_without_select_listbox(): void
    {
        $destination = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);

        $this->actingAs($this->actingAsAgencyManager());

        Livewire::test(Form::class)
            ->set('has_moved', true)
            ->call('selectDestination', $destination->id)
            ->assertSeeHtml('wire:model.live.debounce.350ms="destinationSearch"')
            ->assertSeeHtml('wire:click="selectDestination(null)"')
            ->assertDontSeeHtml('multiple')
            ->assertDontSeeHtml('size="')
            ->assertDontSeeHtml('Selecciona una agencia</option>');
    }

    public function test_agency_destination_selection_persists_moved_to_agency_id(): void
    {
        $destination = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);

        $this->actingAs($this->actingAsAgencyManager());

        $component = Livewire::test(Form::class)
            ->set('has_moved', true)
            ->call('selectDestination', $destination->id)
            ->set('moved_to_address', 'Jr. Nueva 123')
            ->set('move_notice', 'Traslado temporal')
            ->set('moved_at', '2026-07-18');

        $component->assertSet('moved_to_agency_id', $destination->id);
    }

    public function test_agency_destination_selection_can_be_cleared(): void
    {
        $destination = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);

        $this->actingAs($this->actingAsAgencyManager());

        $component = Livewire::test(Form::class)
            ->set('has_moved', true)
            ->call('selectDestination', $destination->id)
            ->call('selectDestination', null);

        $component->assertSet('moved_to_agency_id', null);
        $component->assertSet('destinationSearch', '');
    }

    public function test_agency_destination_search_excludes_current_agency(): void
    {
        $agency = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);

        $this->actingAs($this->actingAsAgencyManager());

        Livewire::test(Form::class, ['agency' => $agency])
            ->set('has_moved', true)
            ->set('destinationSearch', $agency->code)
            ->assertDontSeeHtml($agency->code.' — '.$agency->name);
    }

    public function test_admin_agency_detail_loads(): void
    {
        $agency = Agency::factory()->create(['latitude' => '-12.0463740', 'longitude' => '-77.0427930']);

        $this->actingAs($this->actingAsAgencyManager())
            ->get('/admin/agencies/'.$agency->id)
            ->assertOk()
            ->assertSee($agency->name)
            ->assertSee($agency->code)
            ->assertSee((string) $agency->external_id)
            ->assertSee('Texto Chosen Terrestre')
            ->assertSee('Texto Chosen Aéreo')
            ->assertSee('data-codered-map', false);
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
        $agency = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false, 'latitude' => '-12.0463740', 'longitude' => '-77.0427930']);

        $this->get('/agencies/'.$agency->code)
            ->assertOk()
            ->assertSee($agency->name)
            ->assertSee($agency->code)
            ->assertSee('data-codered-map', false);
    }

    public function test_import_page_loads_for_authorized_user(): void
    {
        $this->actingAs($this->actingAsAgencyManager())
            ->get('/admin/agencies/import')
            ->assertOk()
            ->assertSee('Importar agencias');
    }
}
