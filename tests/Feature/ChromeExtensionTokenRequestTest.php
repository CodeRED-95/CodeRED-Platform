<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChromeExtensionTokenRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_token_request_endpoint_creates_pending_request_with_agencies_scope(): void
    {
        $response = $this->postJson('/api/v1/token-requests', [
            'requester_name' => 'Ada Lovelace',
            'delivery_channel' => 'whatsapp',
            'delivery_destination' => '+51987654321',
            'instance_name' => 'Buscador Shalom Control',
            'source' => 'chrome_extension',
            'requested_scopes' => ['agencies:read'],
            'notes' => 'Solicitud de prueba.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('api_token_requests', [
            'requester_name' => 'Ada Lovelace',
            'request_source' => 'chrome_extension',
            'requested_abilities' => json_encode(['agencies:read']),
        ]);
    }
}
