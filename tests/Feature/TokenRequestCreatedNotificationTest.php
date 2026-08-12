<?php

namespace Tests\Feature;

use App\Events\TokenRequestCreated;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Listeners\SendTokenRequestCreatedWebhook;
use App\Models\ApiTokenRequest;
use App\Models\Integration;
use App\Services\ApiTokens\TokenVaultService;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class TokenRequestCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_token_request_dispatches_domain_event_after_commit(): void
    {
        Event::fake([TokenRequestCreated::class]);
        Queue::fake();

        $csrf = 'token-request-test';

        $this->withSession(['_token' => $csrf])
            ->from('/solicitar-token')
            ->post('/solicitar-token', $this->validPublicPayload(['_token' => $csrf]))
            ->assertRedirect('/solicitar-token');

        $request = ApiTokenRequest::query()->firstOrFail();
        Event::assertDispatched(TokenRequestCreated::class, fn (TokenRequestCreated $event): bool => $event->tokenRequest->is($request) && $event->afterCommit === true);
        Queue::assertNotPushed(NotifyN8nTokenRequestStatus::class, fn ($job): bool => $job->event === 'token_request.created');
    }

    public function test_listener_is_queued_with_retry_configuration(): void
    {
        $request = $this->createTokenRequest();
        $listener = new SendTokenRequestCreatedWebhook;

        $this->assertSame(5, $listener->tries);
        $this->assertSame([10, 30, 60, 180, 300], $listener->backoff());
        $this->assertGreaterThanOrEqual(5, $listener->timeout);
        $this->assertNotEmpty((new TokenRequestCreated($request))->eventId);
    }

    public function test_testing_environment_creates_request_without_external_delivery(): void
    {
        config()->set('services.n8n.token_request_notifications.enabled', true);
        config()->set('services.n8n.token_request_notifications.webhook_url', 'https://n8n.real.example/webhook/codered-token-request');
        config()->set('services.n8n.token_request_notifications.secret', 'test-secret-1234567890');
        config()->set('services.n8n.token_request_notifications.timeout', 10);

        Http::fake(['https://n8n.real.example/*' => Http::response(['success' => true], 200)]);

        $request = $this->createTokenRequest([
            'delivery_whatsapp_number' => '+51999888777',
            'delivery_whatsapp_number_masked' => '+51 ******777',
            'delivery_channel' => 'whatsapp',
        ]);
        $event = new TokenRequestCreated($request, '11111111-1111-4111-8111-111111111111');

        app(SendTokenRequestCreatedWebhook::class)->handle($event);
        Http::assertNothingSent();
        $this->assertDatabaseCount('webhook_deliveries', 0);
        $this->assertDatabaseCount('api_token_request_events', 0);
    }

    public function test_disabled_or_incomplete_configuration_does_not_send_webhook_or_break_request(): void
    {
        config()->set('services.n8n.token_request_notifications.enabled', false);
        Http::fake();

        $request = $this->createTokenRequest();
        app(SendTokenRequestCreatedWebhook::class)->handle(new TokenRequestCreated($request));

        Http::assertNothingSent();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_testing_environment_silences_status_notifications(): void
    {
        config()->set('services.n8n.token_request_notifications.enabled', true);
        config()->set('services.n8n.token_request_notifications.webhook_url', 'https://n8n.example.test/webhook/codered-token-request');
        config()->set('services.n8n.token_request_notifications.secret', 'test-secret-1234567890');

        Http::fake(['https://n8n.example.test/*' => Http::response(['ok' => true], 200)]);

        $request = $this->createTokenRequest([
            'delivery_email' => 'cliente@example.test',
            'delivery_email_masked' => 'c***@example.test',
            'delivery_channel' => 'email',
        ]);
        $event = new TokenRequestCreated($request, '22222222-2222-4222-8222-222222222222');

        app(SendTokenRequestCreatedWebhook::class)->handle($event);
        $job = new NotifyN8nTokenRequestStatus($request->id, 'token_request.approved');
        $integration = Integration::query()->forceCreate([
            'integration_uuid' => (string) Str::uuid(),
            'instance_uuid' => (string) Str::uuid(),
            'provider' => 'n8n',
            'instance_name' => 'n8n test',
            'instance_url' => 'https://n8n.example.test',
            'status' => 'connected',
            'encrypted_secret' => Crypt::encryptString('integration-secret'),
            'last_seen_at' => now(),
            'created_by' => null,
        ]);
        $integration->capabilities()->create([
            'capability' => 'token.request.approved',
            'service' => 'token.request.approved',
            'method' => 'POST',
            'path' => '/webhook/token-request-approved',
            'enabled' => true,
            'checksum' => sha1('token.request.approved|POST|/webhook/token-request-approved|'),
        ]);

        $job->handle(app(IntegrationProtocolService::class));

        Http::assertNothingSent();
        $this->assertDatabaseCount('webhook_deliveries', 0);
        $this->assertDatabaseCount('api_token_request_events', 0);
    }

    private function validPublicPayload(array $overrides = []): array
    {
        return array_merge([
            'requester_name' => 'Cliente Demo',
            'delivery_method' => 'whatsapp',
            'delivery_destination' => '+51999888777',
            'installation_name' => 'Buscador Shalom Control',
            'integration_type' => 'shalom-extension',
            'reason' => 'Necesito sincronizar agencias.',
            'source' => 'shalom-extension',
            'extension_version' => '2.1.0',
            'terms' => '1',
            'website' => '',
        ], $overrides);
    }

    private function createTokenRequest(array $overrides = []): ApiTokenRequest
    {
        $vault = app(TokenVaultService::class);
        $email = $overrides['delivery_email'] ?? null;
        $name = $overrides['requester_name'] ?? 'Cliente Demo';

        unset($overrides['requester_name']);

        return ApiTokenRequest::query()->create(array_merge([
            'request_uuid' => (string) Str::uuid(),
            'tracking_code' => 'CR-'.strtoupper(Str::random(10)),
            'requester_name_encrypted' => $vault->encrypt($name),
            'requester_email_blind_index' => $email ? $vault->generateBlindIndex($email) : null,
            'application_name' => 'Buscador Shalom Control',
            'requested_token_name' => 'Buscador Shalom Control',
            'requested_token_type' => 'agencies',
            'requested_abilities' => ['agencies:read'],
            'requested_expires_in_minutes' => 60,
            'requested_token_expires_in_days' => 30,
            'token_expires_in_days' => 30,
            'status' => 'pending',
            'telegram_user_id' => 'public:test-user',
            'telegram_chat_id' => 'public:test-chat',
            'request_source' => 'shalom-extension',
            'metadata' => ['tracking_code' => 'CR-TEST1234', 'integration_type' => 'shalom-extension'],
            'requested_at' => now(),
            'delivery_status' => 'not_available',
            'delivery_channel' => 'whatsapp',
            'delivery_whatsapp_number' => $vault->encrypt('+51999888777'),
        ], $overrides));
    }
}
