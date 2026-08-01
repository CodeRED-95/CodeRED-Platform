<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChromeExtensionConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_chrome_extension_public_config_exposes_safe_connection_metadata(): void
    {
        config([
            'app.name' => 'CodeRED Platform',
            'app.url' => 'https://platform.test',
            'api.chrome_extension_sync_interval_hours' => 24,
            'api.chrome_extension_token_request_path' => '/solicitar-token',
        ]);

        $response = $this->getJson('/api/v1/extension/chrome/config');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform_name', 'CodeRED Platform')
            ->assertJsonPath('data.api_base_url', 'https://platform.test/api/v1')
            ->assertJsonPath('data.token_request_url', 'https://platform.test/solicitar-token')
            ->assertJsonPath('data.sync_interval_hours', 24)
            ->assertJsonStructure([
                'data' => [
                    'agency_catalog_version',
                    'required_scopes',
                    'endpoints' => [
                        'validate_token',
                        'catalog_metadata',
                        'agencies',
                        'changes',
                    ],
                ],
            ]);

        $payload = $response->json('data');

        $this->assertSame(['agencies:read'], $payload['required_scopes']);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('secret', $payload);
    }
}
