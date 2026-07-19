<?php

namespace Tests\Feature;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Tests\TestCase;

class AgencyApiTest extends TestCase
{
    public function test_agencies_index_returns_success_shape(): void
    {
        Agency::factory()->create(['status' => AgencyStatus::Active]);

        $response = $this->getJson('/api/v1/agencies');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_agency_contract_exposes_external_code_and_chosen_identifiers(): void
    {
        $agency = Agency::factory()->create([
            'status' => AgencyStatus::Active,
            'external_id' => 610,
            'texto_chosen_terrestre' => '610 - TERRESTRE',
            'texto_chosen_aereo' => '610 - AEREO',
        ]);

        $this->getJson('/api/v1/agencies/'.$agency->code)->assertOk()->assertJsonPath('data.id', 610)
            ->assertJsonPath('data.internal_id', $agency->id)->assertJsonPath('data.code', $agency->code)
            ->assertJsonPath('data.texto_chosen_terrestre', '610 - TERRESTRE')
            ->assertJsonPath('data.texto_chosen_aereo', '610 - AEREO')
            ->assertJsonPath('data.texto_chosen', '610 - TERRESTRE');
    }

    public function test_snapshot_uses_external_id_and_keeps_deprecated_chosen_fallback(): void
    {
        $agency = Agency::factory()->create([
            'status' => AgencyStatus::Active, 'has_moved' => false, 'external_id' => 614,
            'texto_chosen_terrestre' => null, 'texto_chosen_aereo' => '614 - AEREO',
        ]);

        $this->getJson('/api/v1/agencies/snapshot')->assertOk()
            ->assertJsonFragment(['id' => 614, 'code' => $agency->code, 'texto_chosen_aereo' => '614 - AEREO', 'texto_chosen' => '614 - AEREO']);
    }

    public function test_agency_version_returns_payload(): void
    {
        $response = $this->getJson('/api/v1/agencies/version');

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => ['version', 'updated_at', 'total_active'],
        ]);
    }
}
