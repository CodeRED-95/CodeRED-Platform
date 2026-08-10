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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenRotationRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authenticated_token_can_request_rotation_and_original_stays_active(): void
    {
        $owner = User::factory()->create();
        $plain = $owner->createToken('Token DNI', ApiTokenType::Dni->abilities(), now()->addDays(20))->plainTextToken;

        $response = $this->withToken($plain)->postJson('/api/v1/token-requests/rotation', [
            'reason' => 'Rotación preventiva',
            'requester_name' => 'Ada Lovelace',
            'idempotency_key' => 'rotation-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.request_type', 'rotation')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.token_type', 'dni')
            ->assertJsonMissingPath('data.token');

        $token = ApiToken::query()->firstOrFail();
        $this->assertDatabaseHas('api_token_requests', [
            'request_type' => 'rotation',
            'source_personal_access_token_id' => $token->id,
            'status' => 'pending',
            'token_type' => 'dni',
        ]);

        $this->assertNull($token->fresh()->revoked_at);
        $this->assertNotSame(401, $this->withToken($plain)->getJson('/api/v1/dni/12345678')->getStatusCode());
    }

    public function test_rotation_is_idempotent_and_blocks_duplicate_pending_request(): void
    {
        $owner = User::factory()->create();
        $plain = $owner->createToken('Token RUC', ApiTokenType::Ruc->abilities(), now()->addDays(20))->plainTextToken;

        $first = $this->withToken($plain)->postJson('/api/v1/token-requests/rotation', [
            'reason' => 'Rotación preventiva',
            'idempotency_key' => 'same-key',
        ])->assertCreated();

        $this->withToken($plain)->postJson('/api/v1/token-requests/rotation', [
            'reason' => 'Reintento por red',
            'idempotency_key' => 'same-key',
        ])->assertOk()
            ->assertJsonPath('data.request_id', $first->json('data.request_id'));

        $this->withToken($plain)->postJson('/api/v1/token-requests/rotation', [
            'reason' => 'Otra solicitud',
            'idempotency_key' => 'different-key',
        ])->assertUnprocessable()
            ->assertJsonPath('error_code', 'rotation_pending_exists');

        $this->assertDatabaseCount('api_token_requests', 1);
    }

    public function test_revoked_or_expired_token_cannot_request_rotation(): void
    {
        $owner = User::factory()->create();
        $expired = $owner->createToken('Token DNI expirado', ApiTokenType::Dni->abilities(), now()->subMinute())->plainTextToken;
        $revoked = $owner->createToken('Token RUC revocado', ApiTokenType::Ruc->abilities(), now()->addDay());
        $revoked->accessToken->forceFill(['revoked_at' => now()])->save();

        $this->withToken($expired)->postJson('/api/v1/token-requests/rotation', ['reason' => 'Expirado'])
            ->assertUnauthorized();

        $this->withToken($revoked->plainTextToken)->postJson('/api/v1/token-requests/rotation', ['reason' => 'Revocado'])
            ->assertUnauthorized();
    }

    public function test_admin_approves_rotation_revokes_original_and_preserves_token_contract(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-07-29 12:00:00');
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $expiresAt = Carbon::parse('2026-08-28 23:59:00');
        $created = $owner->createToken('Token AGENCIAS', ApiTokenType::Agencies->abilities(), $expiresAt);
        $oldPlainToken = $created->plainTextToken;
        $source = ApiToken::query()->findOrFail($created->accessToken->id);
        $request = $this->rotationRequest($source, ['token_type' => 'agencies', 'requested_token_type' => 'agencies']);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->assertSee('Rotación de Token AGENCIAS')
            ->assertDontSee('Vigencia del token en días')
            ->set('adminNote', 'Aprobado')
            ->call('approve')
            ->assertHasNoErrors();

        $request->refresh();
        $source->refresh();
        $replacement = ApiToken::query()->findOrFail($request->replacement_personal_access_token_id);

        $this->assertSame(ApiTokenRequestStatus::Approved->value, $request->statusValue());
        $this->assertSame(ApiTokenRequestDeliveryStatus::Pending->value, $request->deliveryStatusValue());
        $this->assertSame($source->id, $request->source_personal_access_token_id);
        $this->assertSame($replacement->id, $request->personal_access_token_id);
        $this->assertSame($replacement->id, $request->replacement_personal_access_token_id);
        $this->assertNotNull($source->revoked_at);
        $this->assertSame(ApiTokenType::Agencies->abilities(), $replacement->abilities);
        $this->assertSame($owner->id, $replacement->tokenable_id);
        $this->assertSame($source->tokenable_type, $replacement->tokenable_type);
        $this->assertSame($expiresAt->toIso8601String(), $replacement->expires_at?->toIso8601String());
        $this->assertNotNull($request->token_ciphertext);
        $this->withToken($oldPlainToken)->getJson('/api/v1/agencies')->assertUnauthorized();

        $this->assertDatabaseHas('api_token_request_events', [
            'api_token_request_id' => $request->id,
            'event' => 'rotation_approved',
        ]);
        $this->assertStringNotContainsString((string) $request->token_ciphertext, $request->events()->pluck('metadata')->toJson());
    }

    public function test_rotation_preserves_null_expiration_and_retrieve_once(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $created = $owner->createToken('Token DNI sin expiración', ApiTokenType::Dni->abilities(), null);
        $source = ApiToken::query()->findOrFail($created->accessToken->id);
        $request = $this->rotationRequest($source, ['token_type' => 'dni', 'requested_token_type' => 'dni']);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->call('approve')
            ->assertHasNoErrors();

        $request->refresh();
        $replacement = ApiToken::query()->findOrFail($request->replacement_personal_access_token_id);
        $this->assertNull($replacement->expires_at);

        $first = $this->signedRetrieve($request)->assertOk();
        $this->assertNotEmpty($first->json('data.token'));
        $this->assertNotSame(401, $this->withToken((string) $first->json('data.token'))->getJson('/api/v1/dni/12345678')->getStatusCode());
        $this->assertSame('rotation', $first->json('data.request_type'));
        $this->assertNull($first->json('data.expires_at'));

        $this->signedRetrieve($request)->assertConflict()
            ->assertJsonPath('error_code', 'token_already_retrieved');
    }

    public function test_rotation_cannot_be_approved_when_source_expires_while_pending(): void
    {
        Queue::fake();
        $admin = $this->superAdmin();
        $owner = User::factory()->create();
        $created = $owner->createToken('Token RUC', ApiTokenType::Ruc->abilities(), now()->subMinute());
        $source = ApiToken::query()->findOrFail($created->accessToken->id);
        $request = $this->rotationRequest($source, ['token_type' => 'ruc', 'requested_token_type' => 'ruc']);

        Livewire::actingAs($admin)->test(Index::class)
            ->call('selectRequest', $request->id)
            ->call('approve')
            ->assertStatus(422);

        $request->refresh();
        $this->assertSame(ApiTokenRequestStatus::Expired->value, $request->statusValue());
        $this->assertNull($request->replacement_personal_access_token_id);
    }

    private function rotationRequest(ApiToken $source, array $overrides = []): ApiTokenRequest
    {
        return ApiTokenRequest::query()->create(array_merge([
            'request_uuid' => (string) Str::uuid(),
            'request_type' => 'rotation',
            'requester_name' => 'Ada Lovelace',
            'application_name' => $source->name,
            'purpose' => 'Rotación preventiva',
            'telegram_user_id' => 'n8n:rotation',
            'telegram_chat_id' => 'n8n:rotation',
            'requested_token_name' => $source->name,
            'requested_abilities' => $source->abilities,
            'requested_expires_in_minutes' => 60,
            'status' => ApiTokenRequestStatus::Pending,
            'request_source' => 'api',
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
            'source_personal_access_token_id' => $source->id,
        ], $overrides));
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }

    private function signedRetrieve(ApiTokenRequest $request)
    {
        return $this->withHeaders($this->signedHeaders('POST', '/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/retrieve', []))
            ->postJson('/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/retrieve');
    }

    private function signedHeaders(string $method, string $path, array $payload): array
    {
        $secret = 'test-shared-secret';
        $integration = Integration::query()->create([
            'integration_uuid' => (string) Str::uuid(),
            'instance_uuid' => (string) Str::uuid(),
            'provider' => 'n8n',
            'instance_name' => 'n8n Test',
            'instance_url' => 'https://n8n.test',
            'environment' => 'testing',
            'protocol_version' => '1.0',
            'status' => IntegrationStatus::Connected,
            'encrypted_secret' => Crypt::encryptString($secret),
            'last_seen_at' => now(),
            'connected_at' => now(),
        ]);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $nonce = (string) Str::uuid();
        $canonical = app(IntegrationProtocolService::class)->canonicalPayload($method, $path, $timestamp, $nonce, $body ?: '');

        return [
            'X-CodeRED-Integration' => $integration->integration_uuid,
            'X-CodeRED-Timestamp' => $timestamp,
            'X-CodeRED-Nonce' => $nonce,
            'X-CodeRED-Signature' => hash_hmac('sha256', $canonical, $secret),
            'X-CodeRED-Protocol-Version' => '1.0',
            'Accept' => 'application/json',
        ];
    }
}
