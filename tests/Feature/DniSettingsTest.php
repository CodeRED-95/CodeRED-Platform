<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\Dni;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Dni\DniSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DniSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_super_admin_can_view_and_update_settings(): void
    {
        $super = $this->superAdmin();
        $regular = User::factory()->create();

        $this->actingAs($super)->get('/admin/settings/dni')->assertOk()->assertSee('API DNI / PeruDevs');
        $this->actingAs($regular)->get('/admin/settings/dni')->assertForbidden();
        Livewire::actingAs($regular)->test(Dni::class)->assertForbidden();
    }

    public function test_api_key_is_encrypted_never_rendered_and_blank_preserves_it(): void
    {
        $super = $this->superAdmin();
        $secret = 'perudevs-private-key-1234';
        $component = Livewire::actingAs($super)->test(Dni::class)
            ->set('enabled', true)
            ->set('baseUrl', 'https://api.perudevs.test/api/v1/dni/complete')
            ->set('newApiKey', $secret)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('newApiKey', '')
            ->assertDontSee($secret);

        $setting = SystemSetting::query()->where('key', 'dni_perudevs.api_key')->firstOrFail();
        $this->assertTrue($setting->is_encrypted);
        $this->assertNotSame($secret, $setting->value);
        $this->assertSame($secret, Crypt::decryptString((string) $setting->value));

        $cipher = $setting->value;
        $component->set('timeoutSeconds', 12)->set('newApiKey', '')->call('save')->assertHasNoErrors();
        $this->assertSame($cipher, SystemSetting::query()->where('key', 'dni_perudevs.api_key')->value('value'));
        $this->assertStringNotContainsString($secret, $this->actingAs($super)->get('/admin/settings/dni')->getContent());
    }

    public function test_database_settings_override_env_and_env_is_fallback(): void
    {
        config()->set('dni.perudevs.enabled', false);
        config()->set('dni.perudevs.base_url', 'https://env.example');
        $settings = app(DniSettingsService::class);

        $this->assertFalse($settings->enabled());
        $this->assertSame('https://env.example', $settings->baseUrl());

        $settings->save([
            'enabled' => true,
            'base_url' => 'https://db.example',
            'timeout_seconds' => 9,
            'retry_times' => 1,
            'cache_ttl_seconds' => 900,
            'not_found_cache_ttl_seconds' => 90,
            'persist_results' => false,
            'refresh_after_days' => 45,
        ]);

        $this->assertTrue($settings->enabled());
        $this->assertSame('https://db.example', $settings->baseUrl());
        $this->assertSame(9, $settings->timeoutSeconds());
        $this->assertFalse($settings->persistResults());
    }

    public function test_connection_uses_query_api_key_without_bearer_or_audit_leak(): void
    {
        $super = $this->superAdmin();
        app(DniSettingsService::class)->save([
            'enabled' => true,
            'base_url' => 'https://api.perudevs.test/api/v1/dni/complete',
            'timeout_seconds' => 10,
            'retry_times' => 0,
            'cache_ttl_seconds' => 86400,
            'not_found_cache_ttl_seconds' => 300,
            'persist_results' => true,
            'refresh_after_days' => 30,
        ], 'connection-secret');

        Http::fake(['*' => Http::response(['estado' => false, 'mensaje' => 'No encontrado'], 200, ['Content-Type' => 'application/json'])]);
        Livewire::actingAs($super)->test(Dni::class)
            ->set('testDni', '12345678')
            ->call('testConnection')
            ->assertHasNoErrors()
            ->assertSet('testDni', '')
            ->assertSee('not_found')
            ->assertDontSee('connection-secret');

        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && $request['document'] === '12345678'
            && $request['key'] === 'connection-secret'
            && ! $request->hasHeader('Authorization'));
        $this->assertStringNotContainsString('connection-secret', json_encode(ActivityLog::query()->get()->toArray(), JSON_THROW_ON_ERROR));
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }
}
