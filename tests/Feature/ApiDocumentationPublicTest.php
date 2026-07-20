<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\ApiDocumentation;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiDocumentationSettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiDocumentationPublicTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        config()->set('api.docs_enabled', true);
    }

    public function test_public_documentation_routes_and_openapi_exist(): void
    {
        app(ApiDocumentationSettingsService::class)->save(true);

        foreach (['/docs', '/docs/api', '/docs/api/v1', '/docs/api/agencias', '/docs/api/dni', '/docs/api/autenticacion', '/docs/api/errores', '/docs/openapi'] as $path) {
            $this->get($path)->assertOk()->assertSee('API CodeRED Platform');
        }

        $openapi = (string) file_get_contents(base_path('docs/openapi.yaml'));
        $this->assertStringContainsString('/dni/{dni}:', $openapi);
        $this->assertStringContainsString('/agencias:', $openapi);
        $this->assertStringContainsString('scheme: bearer', $openapi);
        $this->assertStringNotContainsString('DNI_PERUDEVS_API_KEY=', $openapi);
    }

    public function test_private_documentation_requires_login_and_only_super_admin_changes_setting(): void
    {
        app(ApiDocumentationSettingsService::class)->save(false);
        $this->get('/docs/api')->assertRedirect('/login');

        $super = $this->roleUser('super-admin');
        $editor = $this->roleUser('editor');
        $this->actingAs($super)->get('/docs/api')->assertOk();
        $this->actingAs($super)->get('/admin/settings/api-documentation')->assertOk();
        $this->actingAs($editor)->get('/admin/settings/api-documentation')->assertForbidden();
        Livewire::actingAs($super)->test(ApiDocumentation::class)->set('public', true)->call('save')->assertHasNoErrors();
        $this->assertTrue(app(ApiDocumentationSettingsService::class)->isPublic());
    }

    public function test_postman_files_are_valid_and_contain_only_placeholders(): void
    {
        foreach (['docs/postman/CodeRED-Platform-API.postman_collection.json', 'docs/postman/CodeRED-Platform.postman_environment.json'] as $path) {
            $contents = (string) file_get_contents(base_path($path));
            $this->assertIsArray(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('private-perudevs', $contents);
            $this->assertStringNotContainsString('Bearer eyJ', $contents);
        }

        $collection = (string) file_get_contents(base_path('docs/postman/CodeRED-Platform-API.postman_collection.json'));
        $this->assertStringContainsString('{{agency_token}}', $collection);
        $this->assertStringContainsString('{{dni_token}}', $collection);
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail());

        return $user;
    }
}
