<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeclaracionJuradaSetupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_creates_client_and_issues_token_with_expected_abilities(): void
    {
        $this->assertDatabaseMissing('api_clients', ['name' => 'Declaración Jurada Shalom']);

        $exitCode = Artisan::call('declaracion-jurada:setup');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Token emitido', $output);

        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();
        $token = $client->tokens()->whereNull('revoked_at')->firstOrFail();
        $this->assertEqualsCanonicalizing(['dni:consultar', 'agencias:consultar'], $token->abilities);
    }

    public function test_running_again_with_correct_abilities_is_a_noop(): void
    {
        Artisan::call('declaracion-jurada:setup');
        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();
        $tokenId = $client->tokens()->whereNull('revoked_at')->firstOrFail()->id;

        $exitCode = Artisan::call('declaracion-jurada:setup');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('Token emitido', $output);
        $this->assertSame($tokenId, $client->tokens()->whereNull('revoked_at')->firstOrFail()->id);
    }

    public function test_outdated_abilities_trigger_automatic_reissue_without_reissue_flag(): void
    {
        // Simula una instalación previa a la integración de agencias: un
        // ApiClient con un token que solo tiene dni:consultar.
        $client = ApiClient::factory()->create(['name' => 'Declaración Jurada Shalom']);
        $oldToken = $client->createToken('declaracion-jurada-dni-bridge', ['dni:consultar']);

        $exitCode = Artisan::call('declaracion-jurada:setup');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Token emitido', $output);
        $this->assertStringContainsString('abilities desactualizadas', $output);

        $this->assertNotNull($oldToken->accessToken->fresh()->revoked_at);
        $newToken = $client->tokens()->whereNull('revoked_at')->firstOrFail();
        $this->assertEqualsCanonicalizing(['dni:consultar', 'agencias:consultar'], $newToken->abilities);
    }

    public function test_reissue_flag_forces_a_new_token_even_if_abilities_already_match(): void
    {
        Artisan::call('declaracion-jurada:setup');
        $client = ApiClient::query()->where('name', 'Declaración Jurada Shalom')->firstOrFail();
        $firstTokenId = $client->tokens()->whereNull('revoked_at')->firstOrFail()->id;

        $exitCode = Artisan::call('declaracion-jurada:setup', ['--reissue' => true]);

        $this->assertSame(0, $exitCode);
        $newToken = $client->tokens()->whereNull('revoked_at')->firstOrFail();
        $this->assertNotSame($firstTokenId, $newToken->id);
    }
}
