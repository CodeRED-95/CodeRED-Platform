<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Index as AgenciesIndex;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Filtros de tres estados del listado de agencias: Todos / Sí / No.
 */
class AgencyListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function seedAgencies(): void
    {
        Agency::factory()->create([
            'code' => 'AG-CON-CHOSEN',
            'name' => 'AGENCIA CON CHOSEN',
            'texto_chosen_terrestre' => 'TERRESTRE-1',
            'texto_chosen_aereo' => 'AEREO-1',
            'old_name' => 'NOMBRE ANTERIOR',
        ]);

        Agency::factory()->create([
            'code' => 'AG-SIN-CHOSEN',
            'name' => 'AGENCIA SIN CHOSEN',
            'texto_chosen_terrestre' => null,
            'texto_chosen_aereo' => null,
            'old_name' => null,
        ]);

        // Cadena vacía: no es "tiene chosen", debe contar como "No".
        Agency::factory()->create([
            'code' => 'AG-CHOSEN-VACIO',
            'name' => 'AGENCIA CHOSEN VACIO',
            'texto_chosen_terrestre' => '',
            'texto_chosen_aereo' => '',
            'old_name' => '',
        ]);
    }

    /** @return array<int, string> */
    private function codesFor(string $filter, string $value): array
    {
        $component = Livewire::actingAs($this->superAdmin())
            ->test(AgenciesIndex::class)
            ->set($filter, $value);

        return $component->viewData('agencies')->getCollection()->pluck('code')->all();
    }

    public function test_chosen_terrestre_filter_supports_todos_si_and_no(): void
    {
        $this->seedAgencies();

        $todos = $this->codesFor('has_chosen_terrestre', '');
        $this->assertContains('AG-CON-CHOSEN', $todos);
        $this->assertContains('AG-SIN-CHOSEN', $todos);
        $this->assertContains('AG-CHOSEN-VACIO', $todos);

        $si = $this->codesFor('has_chosen_terrestre', '1');
        $this->assertSame(['AG-CON-CHOSEN'], $si);

        $no = $this->codesFor('has_chosen_terrestre', '0');
        $this->assertContains('AG-SIN-CHOSEN', $no);
        $this->assertContains('AG-CHOSEN-VACIO', $no);
        $this->assertNotContains('AG-CON-CHOSEN', $no);
    }

    public function test_chosen_aereo_filter_supports_todos_si_and_no(): void
    {
        $this->seedAgencies();

        $this->assertCount(3, $this->codesFor('has_chosen_aereo', ''));

        $this->assertSame(['AG-CON-CHOSEN'], $this->codesFor('has_chosen_aereo', '1'));

        $no = $this->codesFor('has_chosen_aereo', '0');
        $this->assertContains('AG-SIN-CHOSEN', $no);
        $this->assertContains('AG-CHOSEN-VACIO', $no);
        $this->assertNotContains('AG-CON-CHOSEN', $no);
    }

    public function test_changed_name_filter_supports_todos_si_and_no(): void
    {
        $this->seedAgencies();

        $this->assertCount(3, $this->codesFor('has_changed_name', ''));

        $this->assertSame(['AG-CON-CHOSEN'], $this->codesFor('has_changed_name', '1'));

        $no = $this->codesFor('has_changed_name', '0');
        $this->assertContains('AG-SIN-CHOSEN', $no);
        $this->assertContains('AG-CHOSEN-VACIO', $no);
        $this->assertNotContains('AG-CON-CHOSEN', $no);
    }

    public function test_filter_dropdowns_offer_todos_si_and_no(): void
    {
        $this->seedAgencies();

        Livewire::actingAs($this->superAdmin())
            ->test(AgenciesIndex::class)
            ->assertSeeHtml('agencies-chosen-terrestre-filter')
            ->assertSeeHtml('agencies-chosen-aereo-filter')
            ->assertSeeHtml('agencies-changed-name-filter')
            ->assertSee('Chosen Terrestre')
            ->assertSee('Chosen Aéreo')
            ->assertSee('Cambió de nombre');
    }

    /**
     * La búsqueda por Clasificación se retiró del listado.
     */
    public function test_classification_filter_is_no_longer_available(): void
    {
        $this->seedAgencies();

        Livewire::actingAs($this->superAdmin())
            ->test(AgenciesIndex::class)
            ->assertDontSeeHtml('wire:model.live.debounce.400ms="classification_category"');

        $this->assertFalse(
            property_exists(AgenciesIndex::class, 'classification_category'),
            'El componente ya no debe exponer el filtro de clasificación.',
        );
    }
}
