<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\N8n;
use App\Models\Integration;
use App\Models\IntegrationCapability;
use App\Models\IntegrationLog;
use App\Models\IntegrationPlugin;
use App\Models\IntegrationService;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class N8nSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_sees_the_redesigned_n8n_integrations_page(): void
    {
        $super = $this->userWithRole('super-admin');
        $integration = $this->createConnectedIntegration();

        $this->actingAs($super)->get(route('admin.integrations.n8n'))
            ->assertOk()
            ->assertSee('Integraciones n8n')
            ->assertSee('Conectar con n8n')
            ->assertSee('Documentación')
            ->assertSee('n8n Production')
            ->assertSee('Conectado')
            ->assertSee('Último heartbeat')
            ->assertSee('Último discovery')
            ->assertSee('Plugins')
            ->assertSee('Capabilities')
            ->assertSee('Latency')
            ->assertSee('Servicios')
            ->assertSee('agent')
            ->assertSee('Ver todas')
            ->assertSee('Actividad reciente')
            ->assertSee($integration->integration_uuid)
            ->assertSee('Regenerar secreto')
            ->assertSee('Probar conexión')
            ->assertSee('Reconectar');

        Livewire::actingAs($super)
            ->test(N8n::class)
            ->assertHasNoErrors()
            ->assertSee('Integraciones n8n')
            ->assertSee('Conectar con n8n')
            ->assertSee('Documentación')
            ->assertSee('n8n Production');
    }

    public function test_viewer_cannot_access_n8n_settings(): void
    {
        $viewer = $this->userWithRole('viewer');

        $this->actingAs($viewer)->get(route('admin.integrations.n8n'))->assertForbidden();
        Livewire::actingAs($viewer)->test(N8n::class)->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'password' => bcrypt('Secret12345!')]);
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }

    private function createConnectedIntegration(): Integration
    {
        $integration = Integration::query()->create([
            'integration_uuid' => (string) str()->uuid(),
            'instance_uuid' => (string) str()->uuid(),
            'provider' => 'n8n',
            'instance_name' => 'n8n Production',
            'instance_url' => 'https://n8n.codered.lat',
            'hostname' => 'n8n-prod',
            'environment' => 'production',
            'version' => '2.31.4',
            'n8n_version' => '2.31.4',
            'connector_version' => 'codered-agent/1.0.0',
            'protocol_version' => '1.0',
            'status' => 'connected',
            'encrypted_secret' => Crypt::encryptString('integration-secret'),
            'last_seen_at' => Carbon::now(),
            'latency_ms' => 42,
        ]);

        IntegrationService::query()->create(['integration_id' => $integration->id, 'service' => 'agent', 'enabled' => true]);
        IntegrationService::query()->create(['integration_id' => $integration->id, 'service' => 'n8n', 'enabled' => true]);
        IntegrationPlugin::query()->create(['integration_id' => $integration->id, 'plugin_id' => 'code-red-node', 'name' => 'CodeRED Node', 'version' => '1.1.0', 'enabled' => true]);
        IntegrationPlugin::query()->create(['integration_id' => $integration->id, 'plugin_id' => 'telegram-token', 'name' => 'Telegram Token Requests', 'version' => '1.0.0', 'enabled' => true]);
        IntegrationCapability::query()->create(['integration_id' => $integration->id, 'capability' => 'integration.challenge', 'service' => 'integration.challenge', 'method' => 'POST', 'path' => '/api/v1/integrations/n8n/challenge', 'version' => '1.0', 'enabled' => true, 'checksum' => sha1('integration.challenge|POST|/api/v1/integrations/n8n/challenge|1.0')]);
        IntegrationCapability::query()->create(['integration_id' => $integration->id, 'capability' => 'integration.discovery', 'service' => 'integration.discovery', 'method' => 'POST', 'path' => '/api/v1/integrations/n8n/discovery', 'version' => '1.0', 'enabled' => true, 'checksum' => sha1('integration.discovery|POST|/api/v1/integrations/n8n/discovery|1.0')]);
        IntegrationCapability::query()->create(['integration_id' => $integration->id, 'capability' => 'integration.heartbeat', 'service' => 'integration.heartbeat', 'method' => 'POST', 'path' => '/api/v1/integrations/n8n/heartbeat', 'version' => '1.0', 'enabled' => true, 'checksum' => sha1('integration.heartbeat|POST|/api/v1/integrations/n8n/heartbeat|1.0')]);
        IntegrationCapability::query()->create(['integration_id' => $integration->id, 'capability' => 'integration.status', 'service' => 'integration.status', 'method' => 'GET', 'path' => '/api/v1/integrations/n8n/status', 'version' => '1.0', 'enabled' => true, 'checksum' => sha1('integration.status|GET|/api/v1/integrations/n8n/status|1.0')]);
        IntegrationCapability::query()->create(['integration_id' => $integration->id, 'capability' => 'integration.webhook', 'service' => 'integration.webhook', 'method' => 'POST', 'path' => '/api/v1/integrations/n8n/webhook', 'version' => '1.0', 'enabled' => true, 'checksum' => sha1('integration.webhook|POST|/api/v1/integrations/n8n/webhook|1.0')]);
        IntegrationLog::query()->create(['integration_id' => $integration->id, 'event' => 'Heartbeat', 'level' => 'info', 'message' => 'Heartbeat recibido.', 'metadata' => ['latency_ms' => 42], 'created_at' => Carbon::now()->subMinutes(3)]);
        IntegrationLog::query()->create(['integration_id' => $integration->id, 'event' => 'Discovery', 'level' => 'info', 'message' => 'Discovery publicado.', 'metadata' => ['capabilities' => 4], 'created_at' => Carbon::now()->subMinutes(12)]);

        return $integration->loadCount(['capabilities', 'services', 'plugins'])->load(['capabilities', 'services', 'plugins', 'logs' => fn ($query) => $query->latest('created_at')->limit(8)]);
    }
}
