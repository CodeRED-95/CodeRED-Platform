<?php

namespace Tests\Feature;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\Dni\Data\DniData;
use App\Domain\Dni\Exceptions\DniProviderUnavailableException;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DniApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        $this->app->instance(DniProviderInterface::class, new class implements DniProviderInterface
        {
            public int $calls = 0;

            public function find(string $dni): ?DniData
            {
                $this->calls++;

                return $dni === '00000000' ? null : new DniData($dni, 'ANA PEREZ DIAZ', 'ANA', 'PEREZ', 'DIAZ', '1990-01-01', 36);
            }
        });
    }

    public function test_abilities_are_strictly_separated_and_combined_token_can_use_both(): void
    {
        Agency::factory()->create();
        $client = ApiClient::factory()->create();
        $agencies = $client->createToken('Solo agencias', ['agencias:consultar'])->plainTextToken;
        $dni = $client->createToken('Solo DNI', ['dni:consultar'])->plainTextToken;
        $combined = $client->createToken('Combinado', ['agencias:consultar', 'dni:consultar'])->plainTextToken;

        $this->withToken($agencies)->getJson('/api/v1/agencias')->assertOk();
        auth()->forgetGuards();
        $this->withToken($agencies)->getJson('/api/v1/dni/12345678')->assertForbidden();
        auth()->forgetGuards();
        $this->withToken($dni)->getJson('/api/v1/dni/12345678')->assertOk()->assertJsonPath('data.dni', '12345678');
        auth()->forgetGuards();
        $this->withToken($dni)->getJson('/api/v1/agencias')->assertForbidden();
        auth()->forgetGuards();
        $this->withToken($combined)->getJson('/api/v1/agencias')->assertOk();
        auth()->forgetGuards();
        $this->withToken($combined)->getJson('/api/v1/dni/12345678')->assertOk();
    }

    public function test_dni_validation_not_found_provider_failure_and_cache_are_controlled(): void
    {
        $client = ApiClient::factory()->create();
        $token = $client->createToken('DNI', ['dni:consultar'])->plainTextToken;
        foreach (['1234567', '123456789', '1234ABCD'] as $invalid) {
            $this->withToken($token)->getJson('/api/v1/dni/'.$invalid)->assertUnprocessable();
            auth()->forgetGuards();
        }
        $this->withToken($token)->getJson('/api/v1/dni/00000000')->assertNotFound()->assertJsonPath('success', false);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/87654321')->assertOk();
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/87654321')->assertOk();
        $this->assertTrue(Cache::has('dni:lookup:87654321'));

        $this->app->instance(DniProviderInterface::class, new class implements DniProviderInterface
        {
            public function find(string $dni): ?DniData
            {
                throw new DniProviderUnavailableException;
            }
        });
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/11111111')->assertStatus(503)->assertJsonMissing(['exception']);
    }

    public function test_missing_invalid_expired_and_revoked_tokens_return_unauthorized(): void
    {
        $this->getJson('/api/v1/dni/12345678')->assertUnauthorized();
        $this->withToken('invalid')->getJson('/api/v1/dni/12345678')->assertUnauthorized();
        $client = ApiClient::factory()->create();
        $expired = $client->createToken('Expirado', ['dni:consultar'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson('/api/v1/dni/12345678')->assertUnauthorized();
        $created = $client->createToken('Revocado', ['dni:consultar']);
        $created->accessToken->forceFill(['revoked_at' => now()])->save();
        auth()->forgetGuards();
        $this->withToken($created->plainTextToken)->getJson('/api/v1/dni/12345678')->assertUnauthorized();
    }

    public function test_services_have_independent_token_rate_limits_and_safe_audit(): void
    {
        config()->set('dni.rate_limit_per_minute', 1);
        config()->set('api.agency_rate_limit_per_minute', 2);
        $client = ApiClient::factory()->create();
        $created = $client->createToken('Combinado secreto', ['agencias:consultar', 'dni:consultar']);
        RateLimiter::clear('dni:token:'.$created->accessToken->getKey());
        RateLimiter::clear('agencias:token:'.$created->accessToken->getKey());
        $this->withToken($created->plainTextToken)->getJson('/api/v1/dni/12345678')->assertOk();
        auth()->forgetGuards();
        $this->withToken($created->plainTextToken)->getJson('/api/v1/dni/87654321')->assertTooManyRequests();
        auth()->forgetGuards();
        $this->withToken($created->plainTextToken)->getJson('/api/v1/agencias')->assertOk();
        $log = ApiRequestLog::query()->where('service', 'dni')->firstOrFail();
        $this->assertSame(hash('sha256', '12345678'), $log->identifier_hash);
        $this->assertSame($client->id, $log->api_client_id);
        $this->assertStringNotContainsString($created->plainTextToken, json_encode($log->toArray(), JSON_THROW_ON_ERROR));
    }
}
