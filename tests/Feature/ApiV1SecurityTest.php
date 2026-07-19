<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiV1SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_endpoints_require_valid_token_and_ability(): void
    {
        $this->getJson('/api/v1/agencies')->assertUnauthorized()->assertExactJson(['message' => 'No autenticado.']);

        $withoutAbility = $this->token(['profile:read']);
        $this->withToken($withoutAbility)->getJson('/api/v1/agencies')->assertForbidden()
            ->assertExactJson(['message' => 'El token no tiene permiso para realizar esta acción.']);

    }

    public function test_token_with_required_ability_can_access(): void
    {
        $this->withToken($this->token(['agencies:read']))->getJson('/api/v1/agencies')->assertOk();
    }

    public function test_inactive_token_owner_is_rejected(): void
    {
        $user = User::factory()->inactive()->create();
        $token = $user->createToken('Inactivo', ['agencies:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/agencies')->assertUnauthorized()
            ->assertExactJson(['message' => 'No autenticado.']);
    }

    public function test_revoked_and_expired_tokens_return_unauthorized(): void
    {
        $user = User::factory()->create();
        $revoked = $user->createToken('Revocado', ['agencies:read']);
        $plainRevoked = $revoked->plainTextToken;
        $revoked->accessToken->delete();

        $this->withToken($plainRevoked)->getJson('/api/v1/agencies')->assertUnauthorized();

        $expired = $user->createToken('Expirado', ['agencies:read'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson('/api/v1/agencies')->assertUnauthorized()
            ->assertExactJson(['message' => 'El token ha expirado.']);
    }

    public function test_agency_contract_filters_paginates_and_excludes_deleted_records(): void
    {
        $active = Agency::factory()->create([
            'external_id' => 610,
            'code' => 'SHA-000610',
            'name' => 'Yarinacocha',
            'department' => 'Ucayali ',
            'status' => AgencyStatus::Active,
            'texto_chosen_terrestre' => '610 - TERRESTRE',
            'texto_chosen_aereo' => null,
        ]);
        Agency::factory()->create(['status' => AgencyStatus::Inactive, 'texto_chosen_aereo' => 'AEREO']);
        $deleted = Agency::factory()->create(['external_id' => 999]);
        $deleted->delete();
        $headers = ['Authorization' => 'Bearer '.$this->token(['agencies:read'])];

        $response = $this->withHeaders($headers)->getJson('/api/v1/agencies?search=610&status=active&has_terrestrial=1&per_page=1');
        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.internal_id', $active->id)
            ->assertJsonPath('data.0.id', 610)->assertJsonPath('data.0.departamento', 'Ucayali')
            ->assertJsonPath('data.0.texto_chosen_aereo', null)->assertJsonPath('meta.per_page', 1);
        $this->assertSame([
            'internal_id', 'id', 'code', 'agencia', 'departamento', 'provincia', 'distrito', 'direccion',
            'link_mapa', 'tamano', 'texto_chosen_terrestre', 'texto_chosen_aereo',
        ], array_keys($response->json('data.0')));
        $response->assertJsonMissing(['internal_id' => $deleted->id]);
    }

    public function test_metadata_me_detail_validation_and_public_health_contracts(): void
    {
        $agency = Agency::factory()->create(['code' => 'SHA-DETAIL', 'external_id' => 801]);
        $user = User::factory()->create(['name' => 'Cliente API']);
        $token = $user->createToken('Extensión Chrome', ['agencies:read', 'profile:read'])->plainTextToken;

        $this->getJson('/api/v1/health')->assertOk()->assertJsonStructure(['status', 'api_version', 'timestamp'])
            ->assertJsonMissing(['database' => true]);
        $this->withToken($token)->getJson('/api/v1/catalog/metadata')->assertOk()
            ->assertJsonPath('schema_version', 1)->assertJsonPath('available_channels', ['terrestrial', 'air']);
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('name', 'Cliente API')
            ->assertJsonPath('token_name', 'Extensión Chrome')->assertJsonPath('abilities.0', 'agencies:read');
        $this->withToken($token)->getJson('/api/v1/agencies/'.$agency->code)->assertOk()->assertJsonPath('data.id', 801);
        $this->withToken($token)->getJson('/api/v1/agencies?sort=password')->assertUnprocessable()
            ->assertJsonPath('message', 'Los datos proporcionados no son válidos.');
        $this->withToken($token)->getJson('/api/v1/agencies/NO-EXISTE')->assertNotFound()
            ->assertExactJson(['message' => 'Agencia no encontrada.']);
    }

    public function test_rate_limit_is_applied_per_token(): void
    {
        config()->set('api.rate_limit_per_minute', 2);
        RateLimiter::clear('token:1');
        $token = $this->token(['agencies:read']);

        $this->withToken($token)->getJson('/api/v1/agencies')->assertOk();
        $this->withToken($token)->getJson('/api/v1/agencies')->assertOk();
        $this->withToken($token)->getJson('/api/v1/agencies')->assertTooManyRequests()
            ->assertJsonPath('message', 'Se superó el límite de solicitudes.');
    }

    /** @param list<string> $abilities */
    private function token(array $abilities): string
    {
        return User::factory()->create()->createToken('Prueba API', $abilities)->plainTextToken;
    }
}
