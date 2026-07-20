<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_agencies_index_returns_success_shape(): void
    {
        Agency::factory()->create(['status' => AgencyStatus::Active]);

        $response = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/agencies');

        $response->assertOk()->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_agency_contract_exposes_external_code_and_chosen_identifiers(): void
    {
        $agency = Agency::factory()->create([
            'status' => AgencyStatus::Active,
            'external_id' => 610,
            'texto_chosen_terrestre' => '610 - TERRESTRE',
            'texto_chosen_aereo' => '610 - AEREO',
            'is_operations_center' => true,
        ]);

        $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/agencies/'.$agency->code)->assertOk()->assertJsonPath('data.id', 610)
            ->assertJsonPath('data.internal_id', $agency->id)->assertJsonPath('data.code', $agency->code)
            ->assertJsonPath('data.texto_chosen_terrestre', '610 - TERRESTRE')
            ->assertJsonPath('data.texto_chosen_aereo', '610 - AEREO')
            ->assertJsonPath('data.agencia', $agency->name)
            ->assertJsonPath('data.departamento', trim($agency->department))
            ->assertJsonPath('data.provincia', trim($agency->province))
            ->assertJsonPath('data.distrito', trim($agency->district))
            ->assertJsonPath('data.direccion', trim($agency->address))
            ->assertJsonPath('data.link_mapa', $agency->map_url)
            ->assertJsonPath('data.tamano', $agency->size?->label())
            ->assertJsonPath('data.estado', 'Activa')
            ->assertJsonPath('data.centro_operaciones', true);

        $this->assertIsBool($this->withHeaders($this->tokenHeaders())->getJson('/api/v1/agencies/'.$agency->code)->json('data.centro_operaciones'));
    }

    public function test_snapshot_uses_external_id_and_keeps_deprecated_chosen_fallback(): void
    {
        $agency = Agency::factory()->create([
            'status' => AgencyStatus::Active, 'has_moved' => false, 'external_id' => 614,
            'texto_chosen_terrestre' => null, 'texto_chosen_aereo' => '614 - AEREO',
            'is_operations_center' => true,
        ]);

        $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/agencies/snapshot')->assertOk()
            ->assertJsonFragment([
                'internal_id' => $agency->id,
                'id' => 614,
                'code' => $agency->code,
                'estado' => 'Activa',
                'centro_operaciones' => true,
                'texto_chosen_aereo' => '614 - AEREO',
                'texto_chosen' => '614 - AEREO',
            ]);
    }

    public function test_contract_maps_every_real_status_and_boolean_operations_center(): void
    {
        foreach (AgencyStatus::cases() as $index => $status) {
            $agency = Agency::factory()->create([
                'status' => $status,
                'has_moved' => $status === AgencyStatus::Moved,
                'moved_to_address' => $status === AgencyStatus::Moved ? 'Nueva sede' : null,
                'is_operations_center' => $index % 2 === 0,
            ]);

            $response = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/agencies/'.$agency->code)->assertOk();
            $response->assertJsonPath('data.estado', $status->label());
            $this->assertIsBool($response->json('data.centro_operaciones'));
        }
    }

    public function test_agency_version_returns_payload(): void
    {
        $response = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/agencies/version');

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => ['version', 'updated_at', 'total_active'],
        ]);
    }

    /** @return array<string, string> */
    private function tokenHeaders(array $abilities = ['agencies:read']): array
    {
        $token = User::factory()->create()->createToken('Prueba API', $abilities)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}
