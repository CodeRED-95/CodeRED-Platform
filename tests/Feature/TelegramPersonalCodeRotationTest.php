<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\ApiTokenType;
use App\Enums\IntegrationStatus;
use App\Livewire\Admin\ApiTokenRequests\Index;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\Integration;
use App\Models\Role;
use App\Models\User;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramPersonalCodeRotationTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-shared-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.integration_enabled' => true]);
    }

    public function test_approved_telegram_request_links_personal_code_and_endpoint_returns_it(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $request = $this->pendingTelegramIssuance();

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->set('approvalTokenName', 'Telegram DNI')
            ->set('approvalTokenTypes', [ApiTokenType::Dni->value])
            ->set('approvalUserId', $admin->id)
            ->set('tokenExpiresInDays', 30)
            ->call('approve')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertTrue(Str::isUuid((string) $admin->public_code));
        $this->assertSame('123456789', $admin->telegram_user_id);
        $this->assertSame('123456789', $admin->telegram_chat_id);

        $this->signedJson('POST', '/api/v1/integrations/n8n/personal-code', [
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
        ])->assertOk()
            ->assertJsonPath('data.person_code', $admin->public_code)
            ->assertJsonPath('data.display_name', $admin->name)
            ->assertJsonMissingPath('data.token');
    }

    public function test_personal_code_endpoint_rejects_unlinked_telegram_user(): void
    {
        $this->signedJson('POST', '/api/v1/integrations/n8n/personal-code', [
            'telegram_user_id' => '999',
            'telegram_chat_id' => '999',
        ])->assertNotFound()
            ->assertJsonPath('error_code', 'telegram_profile_not_linked');
    }

    public function test_rotation_by_personal_code_creates_pending_request_without_revoking_source_token(): void
    {
        $user = User::factory()->create([
            'public_code' => (string) Str::uuid(),
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
        ]);
        $created = $user->createToken('Telegram DNI', ApiTokenType::Dni->abilities(), now()->addDays(20));
        $source = ApiToken::query()->findOrFail($created->accessToken->id);

        $response = $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/rotation-by-code', [
            'person_code' => $user->public_code,
            'reason' => 'Cambio trimestral',
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
            'idempotency_key' => 'telegram-rotation-123456789-1',
            'source' => 'telegram',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.request_type', 'rotation')
            ->assertJsonPath('data.person_code', $user->public_code)
            ->assertJsonMissingPath('data.token');

        $this->assertNull($source->fresh()->revoked_at);
        $this->assertDatabaseHas('api_token_requests', [
            'request_uuid' => $response->json('data.request_id'),
            'request_type' => 'rotation',
            'source_personal_access_token_id' => $source->id,
            'status' => 'pending',
            'token_type' => 'dni',
        ]);
    }

    public function test_rotation_by_personal_code_is_idempotent(): void
    {
        $user = User::factory()->create([
            'public_code' => (string) Str::uuid(),
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
        ]);
        $user->createToken('Telegram RUC', ApiTokenType::Ruc->abilities(), now()->addDays(20));
        $payload = [
            'person_code' => $user->public_code,
            'reason' => 'Cambio trimestral',
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
            'idempotency_key' => 'telegram-rotation-same-key',
            'source' => 'telegram',
        ];

        $first = $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/rotation-by-code', $payload)
            ->assertCreated();

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/rotation-by-code', $payload)
            ->assertOk()
            ->assertJsonPath('data.request_id', $first->json('data.request_id'));

        $other = User::factory()->create([
            'public_code' => (string) Str::uuid(),
            'telegram_user_id' => '987654321',
            'telegram_chat_id' => '987654321',
        ]);
        $other->createToken('Other DNI', ApiTokenType::Dni->abilities(), now()->addDays(20));

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/rotation-by-code', [
            'person_code' => $other->public_code,
            'reason' => 'Cambio trimestral',
            'telegram_user_id' => '987654321',
            'telegram_chat_id' => '987654321',
            'idempotency_key' => 'telegram-rotation-same-key',
            'source' => 'telegram',
        ])->assertConflict()
            ->assertJsonPath('error_code', 'idempotency_key_conflict');

        $this->assertDatabaseCount('api_token_requests', 1);
    }

    public function test_rotation_by_personal_code_rejects_wrong_telegram_user_and_multiple_tokens(): void
    {
        $user = User::factory()->create([
            'public_code' => (string) Str::uuid(),
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
        ]);
        $user->createToken('Telegram DNI', ApiTokenType::Dni->abilities(), now()->addDays(20));

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/rotation-by-code', [
            'person_code' => $user->public_code,
            'reason' => 'Cambio trimestral',
            'telegram_user_id' => '000',
            'telegram_chat_id' => '123456789',
            'idempotency_key' => 'telegram-rotation-wrong-user',
        ])->assertForbidden()
            ->assertJsonPath('error_code', 'person_code_mismatch');

        $user->createToken('Telegram RUC', ApiTokenType::Ruc->abilities(), now()->addDays(20));

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/rotation-by-code', [
            'person_code' => $user->public_code,
            'reason' => 'Cambio trimestral',
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
            'idempotency_key' => 'telegram-rotation-multiple',
        ])->assertConflict()
            ->assertJsonPath('error_code', 'multiple_active_tokens');
    }

    private function pendingTelegramIssuance(): ApiTokenRequest
    {
        return ApiTokenRequest::query()->create([
            'request_uuid' => (string) Str::uuid(),
            'requester_name' => 'Ada Telegram',
            'application_name' => 'Telegram Bot',
            'purpose' => 'Consultar DNI',
            'telegram_user_id' => '123456789',
            'telegram_chat_id' => '123456789',
            'telegram_username' => 'ada_test',
            'requested_token_name' => 'Telegram DNI',
            'requested_token_type' => ApiTokenType::Dni->value,
            'requested_abilities' => ApiTokenType::Dni->abilities(),
            'requested_token_expires_in_days' => 30,
            'requested_expires_in_minutes' => 43200,
            'status' => ApiTokenRequestStatus::Pending,
            'request_source' => 'telegram',
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
        ]);
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function signedJson(string $method, string $path, array $payload = [])
    {
        $integration = $this->integration();
        $body = $method === 'GET' ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $canonical = app(IntegrationProtocolService::class)->canonicalPayload($method, $path, $timestamp, $nonce, $body ?: '');
        $headers = [
            'X-CodeRED-Integration' => $integration->integration_uuid,
            'X-CodeRED-Timestamp' => $timestamp,
            'X-CodeRED-Nonce' => $nonce,
            'X-CodeRED-Signature' => hash_hmac('sha256', $canonical, $this->secret),
            'X-CodeRED-Protocol-Version' => '1.0',
            'Accept' => 'application/json',
        ];

        return $this->withHeaders($headers)->json($method, $path, $payload);
    }

    private function integration(): Integration
    {
        return Integration::query()->first() ?? Integration::query()->create([
            'integration_uuid' => (string) Str::uuid(),
            'instance_uuid' => (string) Str::uuid(),
            'provider' => 'n8n',
            'instance_name' => 'n8n Test',
            'instance_url' => 'https://n8n.test',
            'environment' => 'testing',
            'protocol_version' => '1.0',
            'status' => IntegrationStatus::Connected,
            'encrypted_secret' => Crypt::encryptString($this->secret),
            'last_seen_at' => now(),
            'connected_at' => now(),
        ]);
    }
}
