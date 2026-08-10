<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemVersionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Versión declarada en la fuente única de verdad, leída sin pasar por la
     * configuración de Laravel: así el test comprueba que la app deriva de
     * composer.json y no que dos constantes coincidan entre sí.
     */
    private function versionFromSourceOfTruth(): string
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        $this->assertIsArray($composer);
        $this->assertArrayHasKey('extra', $composer);
        $this->assertArrayHasKey('version', $composer['extra']);

        return (string) $composer['extra']['version'];
    }

    public function test_version_config_api_and_artisan_command_share_current_version(): void
    {
        $version = $this->versionFromSourceOfTruth();

        $this->assertSame($version, config('version.current'));
        $this->assertSame($version, config('app.version'));

        $this->getJson('/api/v1/version')
            ->assertOk()
            ->assertHeader('X-Application-Version', $version)
            ->assertJsonPath('data.version', $version)
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonMissingPath('data.token');

        $this->artisan('app:version')
            ->expectsOutput($version)
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
            ->assertSee('v'.$this->versionFromSourceOfTruth());
    }

    public function test_current_version_follows_semver(): void
    {
        $version = $this->versionFromSourceOfTruth();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
        $this->assertTrue(Version::isValid($version));
    }

    /**
     * APP_VERSION dejó de consultarse en 3.5.0. Una instalación con la variable
     * heredada en su .env no debe alterar la versión que reporta la app.
     */
    public function test_stale_app_version_env_variable_is_ignored(): void
    {
        putenv('APP_VERSION=1.2.3');
        $_ENV['APP_VERSION'] = '1.2.3';
        $_SERVER['APP_VERSION'] = '1.2.3';

        try {
            Version::forget();

            $this->assertSame($this->versionFromSourceOfTruth(), Version::current());
            $this->assertNotSame('1.2.3', Version::current());
        } finally {
            putenv('APP_VERSION');
            unset($_ENV['APP_VERSION'], $_SERVER['APP_VERSION']);
            Version::forget();
        }
    }

    public function test_version_source_points_at_composer_json(): void
    {
        $this->assertSame(base_path('composer.json'), Version::sourcePath());
        $this->assertFileExists(Version::sourcePath());
        $this->assertSame(Version::sourcePath(), config('version.source'));
    }
}
