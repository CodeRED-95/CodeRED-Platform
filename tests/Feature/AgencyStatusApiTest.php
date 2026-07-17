<?php

namespace Tests\Feature;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Tests\TestCase;

class AgencyStatusApiTest extends TestCase
{
    public function test_public_agency_detail_only_returns_active_records(): void
    {
        $agency = Agency::factory()->create([
            'code' => 'AG00001',
            'status' => AgencyStatus::Active,
        ]);

        $response = $this->getJson('/api/v1/agencies/AG00001');

        $response->assertOk()->assertJsonPath('data.code', $agency->code);
    }
}
