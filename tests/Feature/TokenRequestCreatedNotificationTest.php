<?php

namespace Tests\Feature;

use App\Events\TokenRequestCreated;
use App\Jobs\NotifyN8nTokenRequestStatus;
use App\Listeners\SendTokenRequestCreatedWebhook;
use App\Models\ApiTokenRequest;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_webhook_payload_is_signed_minimized_and_marks_delivery_successful(): void
    {
        config()->set('services.n8n.token_request_notifications.enabled', true);
        config()->set('services.n8n.token_request_notifications.webhook_url', 'https://n8n.example.test/webhook/codered-token-request');
        config()->set('services.n8n.token_request_notifications.secret', 'test-secret-1234567890');
        config()->set('services.n8n.token_request_notifications.timeout', 10);

        Http::fake(['https://n8n.example.test/*' => Http::response(['success' => true], 200)]);

        $request = $this->createTokenRequest([
            'delivery_whatsapp_number' => '+51999888777',
            'delivery_whatsapp_number_masked' => '+51 ******777',
            'delivery_channel' => 'whatsapp',
        ]);
        $event = new TokenRequestCreated($request, '11111111-1111-4111-8111-111111111111');

        app(SendTokenRequestCreatedWebhook::class)->handle($event);

        Http::assertSent(function ($httpRequest) use ($event): bool {
            $body = $httpRequest->body();
            $timestamp = $httpRequest->header('X-CodeRED-Timestamp')[0] ?? '';
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'test-secret-1234567890');
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            return $httpRequest->url() === 'https://n8n.example.test/webhook/codered-token-request'
                && $httpRequest->method() === 'POST'
                && ($httpRequest->header('X-CodeRED-Event')[0] ?? null) === 'token_request.created'
                && ($httpRequest->header('X-CodeRED-Event-Id')[0] ?? null) === $event->eventId
                && ($httpRequest->header('X-CodeRED-Signature')[0] ?? null) === $expected
                && $payload['event'] === 'token_request.created'
                && $payload['event_id'] === $event->eventId
                && $payload['request']['tracking_code'] === 'CR-TEST1234'
                && $payload['request']['masked_contact'] === '+51 ******777'
                && str_contains($payload['request']['admin_url'], '/admin/security/token-requests')
                && ! str_contains($body, '+51999888777')
                && ! str_contains($body, 'plain-token')
                && ! str_contains($body, 'test-secret-1234567890');
        });

        $delivery = WebhookDelivery::query()->where('event_id', $event->eventId)->firstOrFail();
        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->last_status_code);
        $this->assertNotNull($delivery->delivered_at);
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

    public function test_http_failure_records_safe_error_and_rethrows_for_queue_retry(): void
    {
        config()->set('services.n8n.token_request_notifications.enabled', true);
        config()->set('services.n8n.token_request_notifications.webhook_url', 'https://n8n.example.test/webhook/codered-token-request');
        config()->set('services.n8n.token_request_notifications.secret', 'test-secret-1234567890');

        Http::fake(['https://n8n.example.test/*' => Http::response(['error' => 'down'], 500)]);
        $request = $this->createTokenRequest(['delivery_email' => 'cliente@example.test', 'delivery_email_masked' => 'c***@example.test', 'delivery_channel' => 'email']);
        $event = new TokenRequestCreated($request, '22222222-2222-4222-8222-222222222222');

        $this->expectExceptionMessage('Webhook n8n falló con estado 500.');

        try {
            app(SendTokenRequestCreatedWebhook::class)->handle($event);
        } finally {
            $delivery = WebhookDelivery::query()->where('event_id', $event->eventId)->firstOrFail();
            $this->assertSame('failed', $delivery->status);
            $this->assertSame(1, $delivery->attempts);
            $this->assertSame(500, $delivery->last_status_code);
            $this->assertStringNotContainsString('cliente@example.test', (string) $delivery->last_error);
        }
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
        return ApiTokenRequest::query()->create(array_merge([
            'request_uuid' => (string) Str::uuid(),
            'requester_name' => 'Cliente Demo',
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
            'delivery_whatsapp_number' => '+51999888777',
            'delivery_whatsapp_number_masked' => '+51 ******777',
        ], $overrides));
    }
}
