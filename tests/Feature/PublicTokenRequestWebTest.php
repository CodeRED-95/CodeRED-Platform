<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestStatus;
use App\Models\ApiTokenRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PublicTokenRequestWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_is_available_without_authentication(): void
    {
        $this->get('/solicitar-token')
            ->assertOk()
            ->assertSee('Solicitar token de acceso')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Agencia no encontrada');
    }

    public function test_public_valid_post_creates_pending_request_without_agency(): void
    {
        Queue::fake();

        $response = $this->postWithCsrf($this->validPayload());

        $response->assertRedirect(route('public.token-requests.create'));
        $response->assertSessionHas('success', 'Solicitud enviada correctamente.');
        $response->assertSessionHas('tracking_code');

        $request = ApiTokenRequest::query()->firstOrFail();
        $this->assertSame(ApiTokenRequestStatus::Pending->value, $request->statusValue());
        $this->assertSame('Buscador Shalom Control', $request->application_name);
        $this->assertSame(['agencies:read'], $request->requested_abilities);
        $this->assertSame('agencies', $request->requested_token_type);
        $this->assertNull($request->getAttribute('agency_id'));
        $this->assertSame('chrome_extension', $request->request_source);
        $this->assertNotSame('+51987654321', $request->delivered_to);
        $this->assertSame('+51987654321', Crypt::decryptString($request->delivered_to));
        $metadata = $request->metadata;
        $this->assertIsArray($metadata);
        $this->assertStringStartsWith('CR-', (string) $metadata['tracking_code']);
    }

    public function test_public_post_does_not_require_agency_id(): void
    {
        Queue::fake();

        $this->postWithCsrf($this->validPayload(['agency_id' => null]))
            ->assertSessionDoesntHaveErrors(['agency_id']);
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        Queue::fake();

        $this->postWithCsrf($this->validPayload())->assertSessionHasNoErrors();
        $this->postWithCsrf($this->validPayload())
            ->assertSessionHasErrors(['delivery_destination' => 'Ya existe una solicitud pendiente para esta instalación.']);

        $this->assertDatabaseCount('api_token_requests', 1);
    }

    public function test_delivery_validation_messages_are_specific(): void
    {
        $this->from('/solicitar-token')->postWithCsrf($this->validPayload(['delivery_destination' => '987654321']))
            ->assertRedirect('/solicitar-token')
            ->assertSessionHasErrors(['delivery_destination' => 'El número de WhatsApp no es válido.']);
    }

    public function test_public_user_cannot_access_admin_panel(): void
    {
        $this->get('/admin/security/token-requests')
            ->assertRedirect('/login');
    }

    private function postWithCsrf(array $payload)
    {
        $token = 'csrf-token-for-test';

        return $this->withSession(['_token' => $token])->post('/solicitar-token', array_merge($payload, ['_token' => $token]));
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'requester_name' => 'Ada Lovelace',
            'delivery_method' => 'whatsapp',
            'delivery_destination' => '+51987654321',
            'installation_name' => 'Buscador Shalom Control',
            'integration_type' => 'shalom-control-search',
            'reason' => 'Necesito sincronizar agencias.',
            'source' => 'chrome_extension',
            'extension_version' => '1.0.0',
            'installation_uuid' => 'public-installation-1',
            'terms' => '1',
            'website' => '',
        ], $overrides);
    }
}
