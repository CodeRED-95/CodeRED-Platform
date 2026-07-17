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

    public function test_agency_version_returns_payload(): void
    {
        $response = $this->getJson('/api/v1/agencies/version');

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => ['version', 'updated_at', 'total_active'],
        ]);
    }
}
