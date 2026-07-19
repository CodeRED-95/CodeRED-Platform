<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\Map;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgencyMapTest extends TestCase
{
    use RefreshDatabase;

    private function agencyViewer(): User
    {
        $permission = Permission::query()->create(['name' => 'Ver agencias', 'slug' => 'agencies.view', 'description' => null]);
        $role = Role::query()->create(['name' => 'Consulta', 'slug' => 'agency-viewer', 'description' => null, 'is_system' => false]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_authorized_user_can_open_agency_map(): void
    {
        $agency = Agency::factory()->create(['name' => 'Agencia Centro Lima', 'latitude' => '-12.0463740', 'longitude' => '-77.0427930', 'status' => AgencyStatus::Active]);

        $this->actingAs($this->agencyViewer())->get(route('admin.agencies.map'))
            ->assertOk()->assertSee('Mapa de agencias')->assertSee($agency->name)
            ->assertSee('Abrir Google Maps')
            ->assertSee('https://www.google.com/maps/search/', false)
            ->assertSee('-12.046374');
    }

    public function test_user_without_agency_permission_cannot_open_map(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.agencies.map'))->assertForbidden();
    }

    public function test_map_filters_by_search_status_and_department(): void
    {
        $visible = Agency::factory()->create(['name' => 'Agencia Miraflores', 'department' => 'Lima', 'latitude' => '-12.1211000', 'longitude' => '-77.0297000', 'status' => AgencyStatus::Active]);
        Agency::factory()->create(['name' => 'Agencia Arequipa', 'department' => 'Arequipa', 'latitude' => '-16.4090000', 'longitude' => '-71.5375000', 'status' => AgencyStatus::Inactive]);
        $this->actingAs($this->agencyViewer());

        Livewire::test(Map::class)->set('search', 'Miraflores')->set('status', AgencyStatus::Active->value)->set('department', 'Lima')
            ->assertSee($visible->name)->assertDontSee('Agencia Arequipa');
    }

    public function test_nearby_agencies_are_sent_to_leaflet_and_missing_coordinates_are_counted(): void
    {
        Agency::factory()->create(['latitude' => '-12.0463700', 'longitude' => '-77.0427900']);
        Agency::factory()->create(['latitude' => '-12.0499900', 'longitude' => '-77.0499900']);
        Agency::factory()->create(['latitude' => null, 'longitude' => null]);
        $this->actingAs($this->agencyViewer());

        Livewire::test(Map::class)
            ->assertViewHas('mappedCount', 2)
            ->assertViewHas('withoutCoordinates', 1)
            ->assertViewHas('markers', fn (array $markers): bool => count($markers) === 2)
            ->assertSeeHtml('data-codered-agency-map')
            ->assertSee('Centrar mapa en');
    }
}
