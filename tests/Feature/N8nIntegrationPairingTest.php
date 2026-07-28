<?php

namespace Tests\Feature;

use App\Models\Integration;
use App\Services\Integrations\IntegrationProtocolService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class N8nIntegrationPairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pair_repeated_with_same_instance_uuid_does_not_create_duplicate_integration(): void
    {
        $protocol = app(IntegrationProtocolService::class);
        $instanceUuid = (string) Str::uuid();
        $payload = [
            'instance_uuid' => $instanceUuid,
            'instance_name' => 'n8n Production',
            'instance_url' => 'https://n8n.codered.host',
            'environment' => 'production',
            'version' => '2.31.4',
            'connector_version' => 'codered-agent/1.0.0',
            'protocol_version' => '1.0',
        ];

        $firstPairing = $protocol->createPairing('n8n');
        $this->postJson('/api/v1/integrations/n8n/pair', ['pair_code' => $firstPairing->pair_code] + $payload)
            ->assertOk()
            ->assertJsonMissingPath('data.sharedSecret');

        $firstIntegrationUuid = Integration::query()->firstOrFail()->integration_uuid;

        $secondPairing = $protocol->createPairing('n8n');
        $this->postJson('/api/v1/integrations/n8n/pair', ['pair_code' => $secondPairing->pair_code] + $payload)
            ->assertOk()
            ->assertJsonPath('data.integration_uuid', $firstIntegrationUuid);

        $this->assertSame(1, Integration::query()->where('provider', 'n8n')->where('instance_uuid', $instanceUuid)->count());
    }

    public function test_pair_requires_instance_uuid(): void
    {
        $pairing = app(IntegrationProtocolService::class)->createPairing('n8n');

        $this->postJson('/api/v1/integrations/n8n/pair', [
            'pair_code' => $pairing->pair_code,
            'instance_name' => 'n8n Production',
            'instance_url' => 'https://n8n.codered.host',
            'environment' => 'production',
            'connector_version' => 'codered-agent/1.0.0',
            'protocol_version' => '1.0',
        ])->assertUnprocessable();
    }
}
