<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_config_api_and_artisan_command_share_current_version(): void
    {
        $this->assertSame('2.2.0', config('version.current'));
        $this->assertSame('2.2.0', config('app.version'));

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertHeader('X-Application-Version', '2.2.0')
            ->assertJsonPath('data.version', '2.2.0')
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonMissingPath('data.token');

        $this->artisan('app:version')
            ->expectsOutput('2.2.0')
            ->assertExitCode(0);
    }

    public function test_admin_layout_renders_current_version(): void
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('v2.2.0');
    }
}
