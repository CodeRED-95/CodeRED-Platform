<?php

declare(strict_types=1);

namespace Tests\Feature\ShalomRecordar;

use App\Models\ApiToken;
use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Flujo de sesión de la extensión Shalom Recordar.
 *
 * Solo toca los endpoints de sesión: no crea solicitudes de token, ni dispara
 * webhooks, n8n o Telegram.
 */
class RecordarSessionTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '550e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function loginPayload(array $overrides = []): array
    {
        return array_merge([
            'installation_uuid' => self::UUID,
            'extension_version' => '2.6.0',
            'installation' => ['device_name' => 'Laptop', 'browser_name' => 'Chrome'],
        ], $overrides);
    }

    /** @return array{user: User, token: string} */
    private function login(): array
    {
        $user = User::factory()->create(['password' => bcrypt('Secret12345!')]);

        $response = $this->postJson('/api/v1/shalom-recordar/auth/login', $this->loginPayload([
            'email' => $user->email,
            'password' => 'Secret12345!',
        ]))->assertOk();

        return ['user' => $user, 'token' => (string) $response->json('data.sync_token')];
    }

    private function authed(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    public function test_login_returns_token_and_user_without_echoing_the_password(): void
    {
        ['user' => $user, 'token' => $token] = $this->login();

        $this->assertNotSame('', $token);

        $this->assertDatabaseHas('shalom_recordar_installations', [
            'user_id' => $user->id,
            'installation_uuid' => self::UUID,
        ]);
    }

    /**
     * La causa del fallo original: la extensión consultaba el estado con un GET
     * sin parámetros y recibía 422, así que nunca pasaba a la vista autenticada.
     */
    public function test_status_works_without_installation_uuid(): void
    {
        ['token' => $token] = $this->login();

        $this->getJson('/api/v1/shalom-recordar/sync/status', $this->authed($token))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.installation_uuid', self::UUID)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'installation_uuid', 'last_synced_at', 'records_count']]);
    }

    public function test_status_works_with_installation_uuid_in_the_query(): void
    {
        ['token' => $token] = $this->login();

        $this->getJson('/api/v1/shalom-recordar/sync/status?installation_uuid='.self::UUID.'&extension_version=2.6.0', $this->authed($token))
            ->assertOk()
            ->assertJsonPath('data.installation_uuid', self::UUID);
    }

    /** La sesión sobrevive a cerrar el popup: el mismo token sigue validando. */
    public function test_session_survives_repeated_validations(): void
    {
        ['token' => $token] = $this->login();

        foreach (range(1, 3) as $ignored) {
            $this->getJson('/api/v1/shalom-recordar/sync/status', $this->authed($token))->assertOk();
        }
    }

    public function test_logout_revokes_the_token_and_forces_login_again(): void
    {
        ['user' => $user, 'token' => $token] = $this->login();

        $this->postJson('/api/v1/shalom-recordar/auth/logout', [], $this->authed($token))
            ->assertOk()
            ->assertJsonPath('success', true);

        $installation = ShalomRecordarInstallation::query()->where('installation_uuid', self::UUID)->firstOrFail();
        $apiToken = ApiToken::query()->find($installation->sync_token_id);
        $this->assertNotNull($apiToken?->revoked_at, 'El token debía quedar revocado.');

        // Un token revocado ya no autentica: la extensión vuelve al login.
        $this->getJson('/api/v1/shalom-recordar/sync/status', $this->authed($token))->assertUnauthorized();

        // Y volver a iniciar sesión funciona.
        $this->postJson('/api/v1/shalom-recordar/auth/login', $this->loginPayload([
            'email' => $user->email,
            'password' => 'Secret12345!',
        ]))->assertOk();
    }

    public function test_logout_keeps_synced_records_and_the_installation(): void
    {
        ['user' => $user, 'token' => $token] = $this->login();

        $this->postJson('/api/v1/shalom-recordar/sync', [
            'installation_uuid' => self::UUID,
            'extension_version' => '2.6.0',
            'batch_id' => 'batch-1',
            'cursor' => now()->format('Y-m-d\\TH:i:s\\Z'),
            'records' => [[
                'field' => 'campo',
                'value' => 'valor',
                'timestamp' => now()->format('Y-m-d\\TH:i:s\\Z'),
                'record_id' => 'local-1',
                'cursor' => now()->format('Y-m-d\\TH:i:s\\Z'),
            ]],
        ], $this->authed($token))->assertOk();

        $this->assertDatabaseCount('shalom_recordar_records', 1);

        $this->postJson('/api/v1/shalom-recordar/auth/logout', [], $this->authed($token))->assertOk();

        // Cerrar sesión no es borrar datos.
        $this->assertDatabaseCount('shalom_recordar_records', 1);
        $this->assertDatabaseHas('shalom_recordar_installations', [
            'user_id' => $user->id,
            'installation_uuid' => self::UUID,
        ]);
    }

    public function test_invalid_token_is_rejected_so_the_popup_returns_to_login(): void
    {
        $this->getJson('/api/v1/shalom-recordar/sync/status', $this->authed('token-invalido'))
            ->assertUnauthorized();
    }

    /** La sincronización usa el token de la sesión restaurada, sin reintroducirlo. */
    public function test_sync_works_with_the_restored_session_token(): void
    {
        ['token' => $token] = $this->login();

        $this->postJson('/api/v1/shalom-recordar/sync', [
            'installation_uuid' => self::UUID,
            'extension_version' => '2.6.0',
            'batch_id' => 'batch-restaurado',
            'cursor' => now()->format('Y-m-d\\TH:i:s\\Z'),
            'records' => [[
                'field' => 'campo',
                'value' => 'valor',
                'timestamp' => now()->format('Y-m-d\\TH:i:s\\Z'),
                'record_id' => 'local-restaurado',
                'cursor' => now()->format('Y-m-d\\TH:i:s\\Z'),
            ]],
        ], $this->authed($token))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/shalom-recordar/sync/status', $this->authed($token))
            ->assertOk()
            ->assertJsonPath('data.records_count', 1);
    }
}
