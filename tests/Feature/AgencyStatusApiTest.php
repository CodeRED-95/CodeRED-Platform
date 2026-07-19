<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencyStatusApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_agency_detail_returns_non_deleted_record_with_token(): void
    {
        $agency = Agency::factory()->create([
            'code' => 'AG00001',
            'status' => AgencyStatus::Active,
        ]);
        $token = User::factory()->create()->createToken('Prueba', ['agencies:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/agencies/AG00001')
            ->assertOk()->assertJsonPath('data.code', $agency->code);
    }
}
