<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RucApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config()->set('ruc.enabled', true);
    }

    public function test_exact_lookup_validates_ability_and_uses_cache(): void
    {
        RucRecord::query()->create(['ruc' => '20123456789', 'razon_social' => 'COMPAÑÍA ÁRBOL SAC', 'estado' => 'ACTIVO']);

        $token = $this->token(['ruc:consultar']);
        $this->withToken($token)->getJson('/api/v1/ruc/20123456789')
            ->assertOk()->assertJsonPath('data.ruc', '20123456789')->assertJsonPath('meta.cached', false);

        auth()->forgetGuards();
        RucRecord::query()->delete();
        $this->withToken($token)->getJson('/api/v1/ruc/20123456789')
            ->assertOk()->assertJsonPath('meta.cached', true);

        auth()->forgetGuards();
        $this->withToken($this->token(['dni:consultar']))->getJson('/api/v1/ruc/20123456789')->assertForbidden();
        auth()->forgetGuards();
        $this->withToken($this->token(['ruc:consultar']))->getJson('/api/v1/dni/12345678')->assertForbidden();
    }

    public function test_search_requires_its_own_ability_and_is_paginated(): void
    {
        RucRecord::query()->create(['ruc' => '20123456789', 'razon_social' => 'EMPRESA TACNA SAC']);
        RucRecord::query()->create(['ruc' => '20987654321', 'razon_social' => 'EMPRESA LIMA SAC']);

        $this->withToken($this->token(['ruc:buscar']))->getJson('/api/v1/ruc/buscar?razon_social=EMPRESA&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);

        auth()->forgetGuards();
        $this->withToken($this->token(['ruc:consultar']))->getJson('/api/v1/ruc/buscar?razon_social=EMPRESA')->assertForbidden();
    }

    public function test_invalid_and_missing_ruc_are_controlled(): void
    {
        $token = $this->token(['ruc:consultar']);
        $this->withToken($token)->getJson('/api/v1/ruc/123')->assertUnprocessable();
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/ruc/20111111111')->assertNotFound();
    }

    private function token(array $abilities): string
    {
        return ApiClient::factory()->create()->createToken('RUC prueba', $abilities)->plainTextToken;
    }
}
