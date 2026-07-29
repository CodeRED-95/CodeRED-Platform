<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestDeliveryStatus;
use App\Enums\ApiTokenRequestStatus;
use App\Enums\IntegrationStatus;
use App\Models\ApiTokenRequest;
use App\Models\Integration;
use App\Models\User;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class N8nTokenRequestFunctionalTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-shared-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.n8n.integration_enabled' => true]);
    }

    public function test_create_token_request_uses_generic_contract(): void
    {
        $response = $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests', [
            'requester_name' => 'Ada Lovelace',
            'requester_email' => 'ada@example.test',
            'application_name' => 'Operations Bot',
            'purpose' => 'Consultar agencias desde n8n',
            'requested_scopes' => ['agencies:read'],
            'expiration_days' => 1,
            'source' => 'n8n',
            'metadata' => ['workflow' => 'manual-token-request'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.requester_name', 'Ada Lovelace')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('api_token_requests', [
            'requester_email' => 'ada@example.test',
            'application_name' => 'Operations Bot',
            'status' => 'pending',
            'request_source' => 'n8n',
        ]);
    }

    public function test_get_status_returns_safe_request_information(): void
    {
        $request = $this->createPendingRequest();

        $response = $this->signedJson('GET', '/api/v1/integrations/n8n/token-requests/'.$request->request_uuid);
        $response
            ->assertOk()
            ->assertJsonPath('data.request_id', $request->request_uuid)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonMissingPath('data.encrypted_plain_text_token')
            ->assertJsonMissingPath('data.token');
    }

    public function test_pending_request_cannot_retrieve_token(): void
    {
        $request = $this->createPendingRequest();

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/retrieve')
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'token_not_retrievable');
    }

    public function test_approved_token_can_be_retrieved_once(): void
    {
        $request = $this->createApprovedRequest('plain-token-value');

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/retrieve')
            ->assertOk()
            ->assertJsonPath('data.token', 'plain-token-value')
            ->assertJsonPath('data.token_type', 'Bearer');

        $request->refresh();
        $this->assertNull($request->encrypted_plain_text_token);
        $this->assertNotNull($request->result_retrieved_at);
        $this->assertSame(ApiTokenRequestDeliveryStatus::Retrieved->value, $request->deliveryStatusValue());
        $this->assertStringNotContainsString('plain-token-value', $request->events()->pluck('metadata')->toJson());

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/retrieve')
            ->assertConflict()
            ->assertJsonPath('error_code', 'token_already_retrieved');
    }

    public function test_invalid_signature_cannot_retrieve_token(): void
    {
        $request = $this->createApprovedRequest('plain-token-value');

        $this->postJson('/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/retrieve')
            ->assertUnauthorized();
    }

    public function test_confirm_delivery_is_idempotent(): void
    {
        $request = $this->createApprovedRequest();
        $request->forceFill(['result_retrieved_at' => now(), 'encrypted_plain_text_token' => null, 'delivery_status' => ApiTokenRequestDeliveryStatus::Retrieved])->save();

        $payload = ['delivery_channel' => 'manual', 'delivered_to' => 'Ada', 'delivery_metadata' => ['message_id' => 'm1']];

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/delivery', $payload)
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
        $firstAttempts = $request->refresh()->delivery_attempts;
        $firstDeliveredAt = $this->isoDate($request->getAttribute('delivered_at'));

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$request->request_uuid.'/delivery', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'La entrega ya estaba confirmada.');

        $request->refresh();
        $this->assertSame($firstAttempts, $request->delivery_attempts);
        $this->assertSame($firstDeliveredAt, $this->isoDate($request->getAttribute('delivered_at')));
    }

    public function test_cancel_pending_request_and_reject_invalid_transitions(): void
    {
        $pending = $this->createPendingRequest();
        $approved = $this->createApprovedRequest();

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$pending->request_uuid.'/cancel', ['cancellation_reason' => 'No longer needed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$pending->request_uuid.'/cancel')
            ->assertOk()
            ->assertJsonPath('message', 'La solicitud ya estaba cancelada.');

        $this->signedJson('POST', '/api/v1/integrations/n8n/token-requests/'.$approved->request_uuid.'/cancel')
            ->assertConflict()
            ->assertJsonPath('error_code', 'invalid_cancellation_state');
    }

    public function test_missing_request_returns_not_found(): void
    {
        $this->signedJson('GET', '/api/v1/integrations/n8n/token-requests/'.(string) Str::uuid())
            ->assertNotFound();
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        return Carbon::parse((string) $value)->toIso8601String();
    }

    private function createPendingRequest(): ApiTokenRequest
    {
        return ApiTokenRequest::query()->create([
            'request_uuid' => (string) Str::uuid(),
            'requester_name' => 'Ada Lovelace',
            'requester_email' => 'ada@example.test',
            'application_name' => 'Operations Bot',
            'purpose' => 'Consultar agencias',
            'telegram_user_id' => 'n8n:test-user',
            'telegram_chat_id' => 'n8n:test-user',
            'requested_token_name' => 'Operations Bot',
            'requested_abilities' => ['agencies:read'],
            'requested_expires_in_minutes' => 60,
            'status' => ApiTokenRequestStatus::Pending,
            'request_source' => 'n8n',
            'requested_at' => now(),
            'delivery_status' => ApiTokenRequestDeliveryStatus::NotAvailable,
        ]);
    }

    private function createApprovedRequest(string $plainToken = 'plain-token-value'): ApiTokenRequest
    {
        $user = User::factory()->create();
        $token = $user->createToken('Approved test token', ['agencies:read'], now()->addHour())->accessToken;
        $request = $this->createPendingRequest();
        $request->forceFill([
            'status' => ApiTokenRequestStatus::Approved,
            'approved_at' => now(),
            'reviewed_at' => now(),
            'personal_access_token_id' => $token->id,
            'encrypted_plain_text_token' => Crypt::encryptString($plainToken),
            'delivery_status' => ApiTokenRequestDeliveryStatus::Pending,
        ])->save();

        return $request;
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

        if ($method === 'GET') {
            return $this->call('GET', $path, [], [], [], $this->transformHeadersToServerVars($headers), '');
        }

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
