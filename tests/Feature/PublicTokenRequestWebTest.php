<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestStatus;
use App\Livewire\Public\TokenRequestManager;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\TokenVaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PublicTokenRequestWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_is_available_without_authentication(): void
    {
        $this->get('/solicitar-token')
            ->assertOk()
            ->assertSee('Solicitud de token de acceso')
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
        $this->assertSame('whatsapp', $request->delivery_channel);

        $metadata = $request->metadata;
        $this->assertIsArray($metadata);
        $this->assertSame('shalom-control-search', $metadata['integration_type']);
    }

    /**
     * El código de seguimiento se persiste en su propia columna con el formato
     * vigente ("CR-" + 10 caracteres) y coincide con el que se muestra al
     * solicitante.
     */
    public function test_tracking_code_is_persisted_with_current_format(): void
    {
        Queue::fake();

        $response = $this->postWithCsrf($this->validPayload());

        $trackingCode = $response->getSession()->get('tracking_code');
        $request = ApiTokenRequest::query()->firstOrFail();

        $this->assertSame($trackingCode, $request->tracking_code);
        $this->assertMatchesRegularExpression('/^CR-[A-Z0-9]{10}$/', (string) $request->tracking_code);
        $this->assertSame(13, mb_strlen((string) $request->tracking_code));

        $this->assertDatabaseHas('api_token_requests', ['tracking_code' => $trackingCode]);
    }

    /**
     * Los datos personales nunca se guardan en claro: el nombre y el motivo
     * viajan cifrados y el correo solo se indexa de forma ciega.
     */
    public function test_requester_data_is_stored_encrypted_and_blind_indexed(): void
    {
        Queue::fake();

        $this->postWithCsrf($this->validPayload([
            'delivery_method' => 'email',
            'delivery_destination' => 'ada@example.test',
        ]));

        $request = ApiTokenRequest::query()->firstOrFail();
        $vault = new TokenVaultService;

        $this->assertNotNull($request->getAttributes()['requester_name_encrypted']);
        $this->assertNotSame('Ada Lovelace', $request->getAttributes()['requester_name_encrypted']);
        $this->assertSame('Ada Lovelace', $request->requester_name);
        $this->assertSame('Necesito sincronizar agencias.', $request->purpose);

        $this->assertSame(
            $vault->generateBlindIndex('ada@example.test'),
            $request->requester_email_blind_index,
        );
        $this->assertSame('ada@example.test', $vault->decrypt($request->delivery_email));
    }

    public function test_public_post_does_not_require_agency_id(): void
    {
        Queue::fake();

        $this->postWithCsrf($this->validPayload(['agency_id' => null]))
            ->assertSessionDoesntHaveErrors(['agency_id']);
    }

    public function test_duplicate_pending_email_request_is_rejected(): void
    {
        Queue::fake();

        $payload = $this->validPayload([
            'delivery_method' => 'email',
            'delivery_destination' => 'ada@example.test',
        ]);

        $this->postWithCsrf($payload)->assertSessionHasNoErrors();
        $this->postWithCsrf($payload)
            ->assertSessionHasErrors(['delivery_destination' => 'Ya existe una solicitud pendiente con este correo para esta instalación.']);

        $this->assertDatabaseCount('api_token_requests', 1);
    }

    public function test_delivery_validation_messages_are_specific(): void
    {
        $this->from('/solicitar-token')->postWithCsrf($this->validPayload(['delivery_destination' => '987654321']))
            ->assertRedirect('/solicitar-token')
            ->assertSessionHasErrors(['delivery_destination' => 'El número de WhatsApp no es válido.']);
    }

    /**
     * La consulta pública combina tracking_code + índice ciego de correo: ambas
     * columnas deben existir para que el formulario encuentre la solicitud.
     */
    public function test_requester_can_look_up_request_with_tracking_code_and_email(): void
    {
        Queue::fake();

        $this->postWithCsrf($this->validPayload([
            'delivery_method' => 'email',
            'delivery_destination' => 'ada@example.test',
        ]));

        $request = ApiTokenRequest::query()->firstOrFail();

        Livewire::test(TokenRequestManager::class)
            ->set('tracking_code_status', $request->tracking_code)
            ->set('email_status', 'ada@example.test')
            ->call('checkStatus')
            ->assertSet('errorMessage', null)
            ->assertSet('foundRequest.id', $request->id);

        $this->assertDatabaseHas('api_token_request_events', [
            'api_token_request_id' => $request->id,
            'event' => 'public_status_checked',
        ]);
    }

    public function test_lookup_fails_when_email_does_not_match_tracking_code(): void
    {
        Queue::fake();

        $this->postWithCsrf($this->validPayload([
            'delivery_method' => 'email',
            'delivery_destination' => 'ada@example.test',
        ]));

        $request = ApiTokenRequest::query()->firstOrFail();

        Livewire::test(TokenRequestManager::class)
            ->set('tracking_code_status', $request->tracking_code)
            ->set('email_status', 'grace@example.test')
            ->call('checkStatus')
            ->assertSet('foundRequest', null)
            ->assertSet('errorMessage', 'No se encontró una solicitud con los datos proporcionados.');
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
