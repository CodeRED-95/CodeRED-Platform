<?php

namespace Tests\Feature;

use App\Domain\Dni\Data\DniData;
use App\Models\ApiClient;
use App\Models\ApiRequestLog;
use App\Models\DniRecord;
use App\Modules\Agencies\Models\Agency;
use App\Services\Dni\DniCacheService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DniApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config()->set('dni.perudevs.enabled', true);
        config()->set('dni.perudevs.base_url', 'https://api.perudevs.test/api/v1/dni/complete');
        config()->set('dni.perudevs.api_key', 'private-perudevs-key');
        config()->set('dni.perudevs.retry_times', 0);
        config()->set('dni.persist_external_results', true);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_local_database_is_primary_preserves_string_and_calculates_age(): void
    {
        CarbonImmutable::setTestNow('2026-11-16 12:00:00');
        Http::fake();
        $record = DniRecord::factory()->create(['dni' => '00123456', 'nombres' => 'LOCAL', 'fecha_nacimiento' => '1994-11-16']);

        $this->withToken($this->token(['dni:consultar']))->getJson('/api/v1/dni/00123456')
            ->assertOk()
            ->assertJsonPath('data.dni', '00123456')
            ->assertJsonPath('data.nombres', 'LOCAL')
            ->assertJsonPath('data.fecha_nacimiento', '1994-11-16')
            ->assertJsonPath('data.edad', 32)
            ->assertJsonPath('meta.source', 'internal');

        Http::assertNothingSent();
        $this->assertSame('00123456', $record->fresh()->dni);
        $this->assertDatabaseCount('dni_records', 1);
        $this->assertDatabaseHas('api_request_logs', ['source' => 'internal', 'provider_called' => false, 'local_database_hit' => true]);
    }

    public function test_cache_precedes_provider(): void
    {
        config()->set('dni.persist_external_results', false);
        app(DniCacheService::class)->put(new DniData('12345678', 'CACHE PERSONA', 'CACHE', 'PERSONA', ''));

        Http::fake();
        $this->withToken($this->token(['dni:consultar']))->getJson('/api/v1/dni/12345678')
            ->assertOk()
            ->assertJsonPath('meta.source', 'cache');

        Http::assertNothingSent();
        $this->assertDatabaseCount('dni_records', 0);
    }

    public function test_real_perudevs_response_uses_get_query_normalizes_and_persists(): void
    {
        CarbonImmutable::setTestNow('2026-11-16 12:00:00');
        Http::fake(['*' => Http::response($this->validProviderPayload(), 200, ['Content-Type' => 'application/json'])]);
        $token = $this->token(['dni:consultar']);

        $this->withToken($token)->getJson('/api/v1/dni/12345678')
            ->assertOk()
            ->assertJsonPath('meta.source', 'perudevs')
            ->assertJsonPath('data.apellido_paterno', 'JIMENEZ')
            ->assertJsonPath('data.genero', 'M')
            ->assertJsonPath('data.fecha_nacimiento', '1994-11-16')
            ->assertJsonPath('data.edad', 32)
            ->assertJsonPath('data.codigo_verificacion', '8')
            ->assertJsonMissingPath('data.provider_reference');

        $this->assertDatabaseHas('dni_records', [
            'dni' => '12345678',
            'source' => 'perudevs',
            'provider_reference' => '12345678',
            'genero' => 'M',
            'fecha_nacimiento' => '1994-11-16',
            'codigo_verificacion' => '8',
        ]);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request['document'] === '12345678'
            && $request['key'] === 'private-perudevs-key'
            && ! $request->hasHeader('Authorization'));

        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/12345678')->assertOk()->assertJsonPath('meta.source', 'internal');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('dni_records', 1);
    }

    public function test_persist_disabled_keeps_result_only_in_cache(): void
    {
        config()->set('dni.persist_external_results', false);
        Http::fake(['*' => Http::response($this->validProviderPayload('87654321'), 200, ['Content-Type' => 'application/json'])]);
        $token = $this->token(['dni:consultar']);

        $this->withToken($token)->getJson('/api/v1/dni/87654321')->assertOk()->assertJsonPath('meta.source', 'perudevs');
        $this->assertDatabaseCount('dni_records', 0);
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/87654321')->assertOk()->assertJsonPath('meta.source', 'cache');
        Http::assertSentCount(1);
    }

    public function test_not_found_and_estado_false_use_negative_cache(): void
    {
        Http::fake(['*' => Http::response(['estado' => false, 'mensaje' => 'No encontrado', 'resultado' => null], 200, ['Content-Type' => 'application/json'])]);
        $token = $this->token(['dni:consultar']);

        $this->withToken($token)->getJson('/api/v1/dni/00000000')->assertNotFound();
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/dni/00000000')->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_invalid_provider_payloads_are_rejected(): void
    {
        $token = $this->token(['dni:consultar']);
        Http::fakeSequence()
            ->push(['estado' => true, 'mensaje' => 'Encontrado', 'resultado' => []], 200, ['Content-Type' => 'application/json'])
            ->push($this->validProviderPayload('99999999'), 200, ['Content-Type' => 'application/json'])
            ->push('<html>bad</html>', 200, ['Content-Type' => 'text/html']);

        foreach (['11111111', '22222222', '33333333'] as $dni) {
            Cache::clear();
            auth()->forgetGuards();
            $this->withToken($token)->getJson('/api/v1/dni/'.$dni)->assertStatus(502);
        }
    }

    public function test_provider_errors_and_timeout_are_controlled(): void
    {
        $token = $this->token(['dni:consultar']);
        Http::fakeSequence()
            ->push([], 401, ['Content-Type' => 'application/json'])
            ->push([], 403, ['Content-Type' => 'application/json'])
            ->push([], 429, ['Content-Type' => 'application/json'])
            ->push([], 500, ['Content-Type' => 'application/json']);

        foreach ([[401, 503], [403, 503], [429, 503], [500, 503]] as [$providerStatus, $expected]) {
            Cache::clear();
            auth()->forgetGuards();
            $this->withToken($token)->getJson('/api/v1/dni/11111111')->assertStatus($expected);
        }
    }

    public function test_provider_timeout_is_controlled(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->withToken($this->token(['dni:consultar']))->getJson('/api/v1/dni/33333333')->assertStatus(503);
    }

    public function test_disabled_provider_returns_503_without_http_call(): void
    {
        config()->set('dni.perudevs.enabled', false);
        Http::fake();

        $this->withToken($this->token(['dni:consultar']))->getJson('/api/v1/dni/12345678')->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_abilities_remain_strictly_separated_and_combined_token_works(): void
    {
        Agency::factory()->create();
        DniRecord::factory()->create(['dni' => '12345678']);
        $client = ApiClient::factory()->create();
        $agencies = $client->createToken('Agencias', ['agencias:consultar']);
        $dni = $client->createToken('DNI', ['dni:consultar']);
        $combined = $client->createToken('Ambos', ['agencias:consultar', 'dni:consultar']);

        $this->withToken($agencies->plainTextToken)->getJson('/api/v1/dni/12345678')->assertForbidden();
        auth()->forgetGuards();
        $this->withToken($dni->plainTextToken)->getJson('/api/v1/agencias')->assertForbidden();
        auth()->forgetGuards();
        $this->withToken($combined->plainTextToken)->getJson('/api/v1/dni/12345678')->assertOk();
        auth()->forgetGuards();
        $this->withToken($combined->plainTextToken)->getJson('/api/v1/agencias')->assertOk();
    }

    public function test_validation_and_audit_never_expose_credentials(): void
    {
        $token = $this->token(['dni:consultar']);
        foreach (['1234567', '123456789', '1234ABCD', '1234-678'] as $invalid) {
            $this->withToken($token)->getJson('/api/v1/dni/'.$invalid)->assertUnprocessable();
            auth()->forgetGuards();
        }

        $logs = json_encode(ApiRequestLog::query()->get()->toArray(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($token, $logs);
        $this->assertStringNotContainsString('private-perudevs-key', $logs);
    }

    private function token(array $abilities): string
    {
        return ApiClient::factory()->create()->createToken('Prueba', $abilities)->plainTextToken;
    }

    private function validProviderPayload(string $dni = '12345678'): array
    {
        return [
            'estado' => true,
            'mensaje' => 'Encontrado',
            'resultado' => [
                'id' => $dni,
                'nombres' => 'MARIA ISABEL',
                'apellido_paterno' => 'JIMENEZ',
                'apellido_materno' => 'DIAZ',
                'nombre_completo' => 'MARIA ISABEL JIMENEZ DIAZ',
                'genero' => 'M',
                'fecha_nacimiento' => '16/11/1994',
                'codigo_verificacion' => '8',
            ],
        ];
    }
}
