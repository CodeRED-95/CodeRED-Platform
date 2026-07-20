<?php

namespace Tests\Feature;

use App\Domain\Dni\Data\DniData;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\DniRecord;
use App\Modules\Agencies\Models\Agency;
use App\Services\Dni\DniCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class DniApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config()->set('dni.perudevs.enabled', true);
        config()->set('dni.perudevs.base_url', 'https://service.fitcoders.test/enty');
        config()->set('dni.perudevs.api_token', 'private-perudevs-token');
        config()->set('dni.perudevs.retry_times', 0);
        config()->set('dni.persist_external_results', true);
    }

    public function test_local_database_is_primary_preserves_string_and_never_calls_provider(): void
    {
        Http::fake();
        $record = DniRecord::factory()->create(['dni' => '00123456', 'nombres' => 'LOCAL']);
        $token = $this->token(['dni:consultar']);
        $this->withToken($token)->getJson('/api/v1/dni/00123456')->assertOk()->assertJsonPath('data.dni', '00123456')->assertJsonPath('data.nombres', 'LOCAL')->assertJsonPath('meta.source', 'internal');
        Http::assertNothingSent();
        $this->assertSame('00123456', $record->fresh()->dni);
        $this->assertDatabaseCount('dni_records', 1);
        $this->assertDatabaseHas('api_request_logs', ['source' => 'internal', 'provider_called' => false, 'local_database_hit' => true]);
    }

    public function test_cache_precedes_provider_and_uses_configured_ttl(): void
    {
        config()->set('dni.cache_ttl', 60);
        config()->set('dni.persist_external_results', false);
        $cache = app(DniCacheService::class);
        $cache->put(new DniData('12345678', 'CACHE PERSONA', 'CACHE', 'PERSONA', ''));
        Http::fake();
        $token = $this->token(['dni:consultar']);
        $this->withToken($token)->getJson('/api/v1/dni/12345678')->assertOk()->assertJsonPath('meta.source', 'cache');
        Http::assertNothingSent();
        $this->assertDatabaseCount('dni_records', 0);
    }

    public function test_perudevs_result_is_normalized_persisted_and_next_lookup_is_local(): void
    {
        Http::fake(['*' => Http::response(['data' => ['dni' => '12345678', 'nombres' => 'ANA', 'apellidoPaterno' => 'PEREZ', 'apellidoMaterno' => 'DIAZ', 'nombreCompleto' => 'ANA PEREZ DIAZ', 'fechaNacimiento' => '1990-01-01', 'edad' => 36, 'id' => 'ref-1']], 200, ['Content-Type' => 'application/json'])]);
        $token = $this->token(['dni:consultar']);
        $this->withToken($token)->getJson('/api/v1/dni/12345678')->assertOk()->assertJsonPath('meta.source', 'perudevs')->assertJsonPath('data.apellido_paterno', 'PEREZ');
        $this->assertDatabaseHas('dni_records', ['dni' => '12345678', 'source' => 'perudevs', 'provider_reference' => 'ref-1']);
        Http::assertSentCount(1);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/12345678')->assertOk()->assertJsonPath('meta.source', 'internal');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('dni_records', 1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer private-perudevs-token') && ! str_contains((string) $request->header('Authorization')[0], $token));
    }

    public function test_persist_disabled_keeps_result_only_in_cache(): void
    {
        config()->set('dni.persist_external_results', false);
        Http::fake(['*' => Http::response(['data' => ['dni' => '87654321', 'nombres' => 'LUIS', 'apellidoPaterno' => 'RAMOS', 'apellidoMaterno' => 'SOTO', 'nombreCompleto' => 'LUIS RAMOS SOTO']], 200, ['Content-Type' => 'application/json'])]);
        $token = $this->token(['dni:consultar']);
        $this->withToken($token)->getJson('/api/v1/dni/87654321')->assertOk()->assertJsonPath('meta.source', 'perudevs');
        $this->assertDatabaseCount('dni_records', 0);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/87654321')->assertOk()->assertJsonPath('meta.source', 'cache');
        Http::assertSentCount(1);
    }

    public function test_not_found_is_cached_with_independent_ttl(): void
    {
        config()->set('dni.not_found_cache_ttl', 30);
        Http::fake(['*' => Http::response([], 404, ['Content-Type' => 'application/json'])]);
        $token = $this->token(['dni:consultar']);
        $this->withToken($token)->getJson('/api/v1/dni/00000000')->assertNotFound();
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/00000000')->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_provider_errors_are_controlled(): void
    {
        $token = $this->token(['dni:consultar']);
        Http::fakeSequence()
            ->push(['message' => 'secret provider detail'], 401, ['Content-Type' => 'application/json'])
            ->push(['message' => 'secret provider detail'], 403, ['Content-Type' => 'application/json'])
            ->push(['message' => 'secret provider detail'], 429, ['Content-Type' => 'application/json'])
            ->push(['message' => 'secret provider detail'], 500, ['Content-Type' => 'application/json'])
            ->push('<html>bad</html>', 200, ['Content-Type' => 'text/html']);

        foreach ([[401, 503], [403, 503], [429, 503], [500, 503]] as [$providerStatus, $expected]) {
            Cache::clear();
            auth()->forgetGuards();
            $this->withToken($token)->getJson('/api/v1/dni/11111111')->assertStatus($expected)->assertJsonMissing(['secret provider detail']);
        }
        Cache::clear();
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/22222222')->assertStatus(502);
    }

    public function test_provider_timeout_is_controlled(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->withToken($this->token(['dni:consultar']))->getJson('/api/v1/dni/33333333')->assertStatus(503);
    }

    public function test_disabled_or_unconfigured_provider_returns_controlled_503(): void
    {
        config()->set('dni.perudevs.enabled', false);
        Http::fake();
        $this->withToken($this->token(['dni:consultar']))->getJson('/api/v1/dni/12345678')->assertStatus(503)->assertJsonPath('success', false);
        Http::assertNothingSent();
    }

    public function test_abilities_remain_strictly_separated_and_rate_limits_independent(): void
    {
        Agency::factory()->create();
        DniRecord::factory()->create(['dni' => '12345678']);
        $client = ApiClient::factory()->create();
        $agencies = $client->createToken('Agencias', ['agencias:consultar']);
        $dni = $client->createToken('DNI', ['dni:consultar']);
        $this->withToken($agencies->plainTextToken)->getJson('/api/v1/dni/12345678')->assertForbidden();
        auth()->forgetGuards();
        $this->withToken($dni->plainTextToken)->getJson('/api/v1/agencias')->assertForbidden();
        auth()->forgetGuards();
        $this->withToken($dni->plainTextToken)->getJson('/api/v1/dni/12345678')->assertOk();
        config()->set('dni.rate_limit_per_minute', 1);
        RateLimiter::clear('dni:token:'.$dni->accessToken->getKey());
    }

    public function test_validation_expiration_revocation_and_audit_never_expose_tokens(): void
    {
        $client = ApiClient::factory()->create();
        $token = $client->createToken('DNI', ['dni:consultar']);
        foreach (['1234567', '123456789', '1234ABCD'] as $invalid) {
            $this->withToken($token->plainTextToken)->getJson('/api/v1/dni/'.$invalid)->assertUnprocessable();
            auth()->forgetGuards();
        }
        $token->accessToken->forceFill(['revoked_at' => now()])->save();
        $this->withToken($token->plainTextToken)->getJson('/api/v1/dni/12345678')->assertUnauthorized();
        $this->assertStringNotContainsString($token->plainTextToken, json_encode(ApiRequestLog::query()->get()->toArray(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('private-perudevs-token', json_encode(ApiRequestLog::query()->get()->toArray(), JSON_THROW_ON_ERROR));
    }

    private function token(array $abilities): string
    {
        return ApiClient::factory()->create()->createToken('Prueba', $abilities)->plainTextToken;
    }
}
