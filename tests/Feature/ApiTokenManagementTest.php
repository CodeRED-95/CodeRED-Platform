<?php

namespace Tests\Feature;

use App\Enums\ApiTokenType;
use App\Livewire\Admin\ApiTokens\Index;
use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApiDocumentationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_administrator_can_manage_tokens_and_authenticated_users_can_read_private_documentation(): void
    {
        $super = $this->superAdmin();
        $viewer = User::factory()->create();

        $this->actingAs($super)->get(route('admin.api-tokens.index'))->assertOk()->assertSee('API y Tokens');
        $this->actingAs($super)->get(route('api.docs'))->assertOk()->assertSee('API CodeRED Platform');
        $this->actingAs($super)->get(route('api.docs.spec'))->assertOk()->assertHeader('content-type', 'application/yaml; charset=UTF-8');
        $this->actingAs($viewer)->get(route('admin.api-tokens.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('api.docs'))->assertOk();
        auth()->forgetGuards();
        $this->get(route('api.docs'))->assertRedirect(route('login'));
        app(ApiDocumentationSettingsService::class)->save(true);
        $this->get(route('api.docs'))->assertOk();
    }

    public function test_super_administrator_creates_token_once_with_safe_metadata(): void
    {
        $super = $this->superAdmin();
        $owner = User::factory()->create();
        $component = Livewire::actingAs($super)->test(Index::class)
            ->set('name', 'Extensión Chrome')
            ->set('description', 'Equipo principal')
            ->set('targetUserId', $owner->id)
            ->set('tokenExpiresInDays', 30)
            ->set('abilities', ['agencies:read'])
            ->call('createToken')
            ->assertHasNoErrors()
            ->assertSet('createdTokenName', 'Extensión Chrome');

        $plain = $component->get('plainTextToken');
        $this->assertIsString($plain);
        $this->assertStringContainsString('|', $plain);
        $token = ApiToken::query()->sole();
        $this->assertNotSame($plain, $token->token);
        $this->assertSame(['agencies:read'], $token->abilities);
        $this->assertSame('Equipo principal', $token->description);
        $this->assertSame($super->id, $token->created_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'api_token_created', 'auditable_id' => $token->id]);
        $this->assertStringNotContainsString($plain, json_encode(ActivityLog::query()->latest('id')->first()?->new_values, JSON_THROW_ON_ERROR));

        $component->call('dismissPlainToken')->assertSet('plainTextToken', null);
        Livewire::actingAs($super)->test(Index::class)->assertDontSee($plain);
    }

    public function test_invalid_token_type_is_rejected(): void
    {
        $super = $this->superAdmin();

        Livewire::actingAs($super)->test(Index::class)
            ->set('name', 'Peligroso')
            ->set('targetUserId', $super->id)
            ->set('abilities', ['not-a-real-ability'])
            ->call('createToken')
            ->assertHasErrors(['abilities.0']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_shalom_recordar_token_type_is_available_with_canonical_minimal_ability(): void
    {
        $type = ApiTokenType::ShalomRecordar;

        $this->assertSame('Token SHALOM RECORDAR', $type->label());
        $this->assertSame('Permite sincronizar datos de Shalom Recordar Extension con CodeRED Platform.', $type->description());
        $this->assertSame(['shalom-recordar:sync'], $type->abilities());
        $this->assertContains('shalom-recordar:sync', ApiTokenType::allowedAbilities());
        $this->assertContains('shalom-recordar', ApiTokenType::values());
        $this->assertContains(
            ['value' => 'shalom-recordar', 'label' => 'Token SHALOM RECORDAR', 'description' => 'Permite sincronizar datos de Shalom Recordar Extension con CodeRED Platform.', 'abilities' => ['shalom-recordar:sync']],
            ApiTokenType::options()
        );
    }

    public function test_manual_generation_maps_each_token_type_to_canonical_abilities(): void
    {
        $super = $this->superAdmin();
        $owner = User::factory()->create();

        foreach (ApiTokenType::cases() as $type) {
            Livewire::actingAs($super)->test(Index::class)
                ->set('name', $type->label())
                ->set('targetUserId', $owner->id)
                ->set('abilities', $type->abilities())
                ->call('createToken')
                ->assertHasNoErrors();

            $token = ApiToken::query()->latest('id')->firstOrFail();
            $this->assertSame($type->abilities(), $token->abilities);
        }
    }

    public function test_super_administrator_can_create_token_with_multiple_abilities(): void
    {
        $super = $this->superAdmin();
        $owner = User::factory()->create();

        Livewire::actingAs($super)->test(Index::class)
            ->set('name', 'Integración multi')
            ->set('targetUserId', $owner->id)
            ->set('abilities', ['dni:consultar', 'ruc:consultar'])
            ->call('createToken')
            ->assertHasNoErrors()
            ->assertSet('createdTokenName', 'Integración multi');

        $token = ApiToken::query()->latest('id')->firstOrFail();
        $this->assertSame(['dni:consultar', 'ruc:consultar'], $token->abilities);
    }

    public function test_token_list_shows_multiple_ability_badges(): void
    {
        $super = $this->superAdmin();
        $owner = User::factory()->create();
        $owner->createToken('Multi', ['dni:consultar', 'ruc:consultar'], now()->addDays(10));

        Livewire::actingAs($super)->test(Index::class)
            ->assertSee('dni:consultar')
            ->assertSee('ruc:consultar')
            ->assertSee('Abilities');
    }

    public function test_admin_api_token_store_accepts_multiple_abilities_and_blocks_unowned_ones(): void
    {
        $manager = User::factory()->create();
        $role = Role::query()->firstOrCreate(['slug' => 'token-manager'], ['name' => 'Token Manager', 'is_system' => false]);
        $role->permissions()->sync(Permission::query()->whereIn('slug', ['api-tokens.create-for-users', 'api-tokens.view-any', 'dni-records.view'])->pluck('id'));
        $manager->roles()->attach($role);
        $token = $manager->createToken('Admin API', ['admin:tokens'])->plainTextToken;
        $owner = User::factory()->create();

        $this->withToken($token)->postJson('/api/v1/admin/tokens', [
            'nombre' => 'Token mixto',
            'tipo' => 'agencies',
            'vigencia_dias' => 30,
            'usuario_id' => $owner->id,
            'abilities' => ['dni:consultar', 'ruc:consultar'],
        ])->assertForbidden();

        $this->withToken($token)->postJson('/api/v1/admin/tokens', [
            'nombre' => 'Token DNI',
            'tipo' => 'dni',
            'vigencia_dias' => 30,
            'usuario_id' => $owner->id,
            'abilities' => ['dni:consultar'],
        ])->assertCreated()
            ->assertJsonPath('data.detalle.abilities', ['dni:consultar']);
    }

    public function test_rotation_preserves_old_token_until_explicit_revocation(): void
    {
        $super = $this->superAdmin();
        $old = $super->createToken('Extensión', ['agencies:read'], now()->addDays(20));
        $oldId = $old->accessToken->getKey();

        $component = Livewire::actingAs($super)->test(Index::class)->call('rotateToken', $oldId);
        $component->assertSet('createdTokenName', 'Extensión (rotado)');
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $oldId]);

        $component->call('revokeToken', $oldId);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldId]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_bulk_revocation_is_limited_audited_and_clears_selection(): void
    {
        $super = $this->superAdmin();
        $ids = collect(range(1, 3))->map(fn (int $number) => $super->createToken('Token '.$number, ['agencies:read'])->accessToken->getKey())->all();

        Livewire::actingAs($super)->test(Index::class)
            ->set('selectedTokenIds', $ids)
            ->call('revokeSelected')
            ->assertSet('selectedTokenIds', []);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(3, ActivityLog::query()->where('action', 'api_token_bulk_revoked')->count());
    }

    public function test_last_used_and_expiration_are_rendered_without_hash(): void
    {
        $super = $this->superAdmin();
        $created = $super->createToken('Visible seguro', ['agencies:read'], now()->addDays(2));
        $token = ApiToken::query()->findOrFail($created->accessToken->getKey());
        $token->forceFill(['last_used_at' => now()->subHour()])->save();

        Livewire::actingAs($super)->test(Index::class)
            ->assertSee('Visible seguro')
            ->assertSee('Próximo a expirar')
            ->assertDontSee($token->token);
    }

    public function test_openapi_contract_and_cors_configuration_are_safe(): void
    {
        $super = $this->superAdmin();
        $contents = file_get_contents(base_path('docs/openapi.yaml'));
        $this->assertIsString($contents);
        $this->assertStringContainsString('openapi: 3.0.3', $contents);
        $this->assertStringContainsString('/agencies:', $contents);
        $this->assertStringNotContainsString('Bearer 1|', $contents);

        config()->set('api.allowed_origins', ['chrome-extension://extension-id']);
        config()->set('cors.allowed_origins', ['chrome-extension://extension-id']);
        $this->actingAs($super)->withHeaders([
            'Origin' => 'chrome-extension://extension-id',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/agencies')->assertHeader('Access-Control-Allow-Origin', 'chrome-extension://extension-id');

        $this->actingAs($super)->withHeaders([
            'Origin' => 'chrome-extension://extension-id',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/v1/shalom-recordar/sync')
            ->assertHeader('Access-Control-Allow-Origin', 'chrome-extension://extension-id');
    }

    public function test_token_copy_control_uses_safe_frontend_component_and_fallback(): void
    {
        $view = file_get_contents(resource_path('views/livewire/admin/api-tokens/index.blade.php'));
        $script = file_get_contents(resource_path('js/api-token-copy.js'));

        $this->assertIsString($view);
        $this->assertIsString($script);
        $this->assertStringContainsString('codeRedTokenCopy(@js($plainTextToken))', $view);
        $this->assertStringContainsString('x-on:click="copy"', $view);
        $this->assertStringContainsString('x-on:click="select"', $view);
        $this->assertStringContainsString('clipboard.writeText(token)', $script);
        $this->assertStringContainsString('selectNodeContents(element)', $script);
        $this->assertStringContainsString('Token copiado correctamente.', $script);
        $this->assertStringNotContainsString('localStorage', $script);
        $this->assertStringNotContainsString('sessionStorage', $script);
        $this->assertStringNotContainsString('console.', $script);
    }

    public function test_interactive_documentation_renders_cards_and_keeps_swagger_as_a_lazy_advanced_view(): void
    {
        $super = $this->superAdmin();
        $response = $this->actingAs($super)->get(route('api.docs'));

        $response->assertOk()
            ->assertSee('API CodeRED Platform')
            ->assertSee('Guía interactiva')
            ->assertSee('OpenAPI avanzada')
            ->assertSee('Estado de autenticación')
            ->assertSee('Comprobar token')
            ->assertSee('Endpoints disponibles')
            ->assertSee('Buscar endpoint')
            ->assertSee('codeRedApiDocs', false)
            ->assertSee("basePath: '/api/v1'", false)
            ->assertSee('autocomplete="off"', false)
            ->assertDontSee('http://192.168.18.124:8090', false)
            ->assertDontSee('http://platform.codered.lat', false);

        $script = file_get_contents(resource_path('js/api-docs.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString('async mountSwagger()', $script);
        $this->assertStringContainsString('if (this.swagger || !this.$refs.swagger) return;', $script);
        $this->assertStringContainsString('persistAuthorization: false', $script);
        $this->assertStringContainsString('tryItOutEnabled: true', $script);
        $this->assertStringContainsString('Authorization: Bearer TU_TOKEN', $script);
        $this->assertStringContainsString('requestTarget: "/api/v1/me"', $script);
        $this->assertStringContainsString('fetchImpl(requestTarget', $script);
        $this->assertStringContainsString('buildApiHeaders', $script);
        $this->assertStringContainsString('this.$store.apiDocsAuth', $script);
        $this->assertStringContainsString('normalizeBearerToken', $script);
        $this->assertStringContainsString('endpointAccess', $script);
        $this->assertStringContainsString('abilitiesKnown', $script);
        $this->assertStringNotContainsString('Token válido, pero sin permiso profile:read', $script);
        $this->assertStringContainsString('options.credentials = "omit"', $script);
        $this->assertStringContainsString('request.credentials = "omit"', $script);
        $this->assertStringContainsString('parseResponseBody', $script);
        $this->assertStringContainsString('AbortError', $script);
        $this->assertStringNotContainsString('localStorage', $script);
        $this->assertStringNotContainsString('sessionStorage', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }

    public function test_documentation_uses_relative_openapi_and_api_urls_behind_https_proxy(): void
    {
        $super = $this->superAdmin();
        $response = $this->actingAs($super)->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'platform.codered.lat',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/docs/api');

        $response->assertOk()
            ->assertSee('/docs/api/openapi.yaml', false)
            ->assertSee("basePath: '/api/v1'", false)
            ->assertDontSee('http://platform.codered.lat', false)
            ->assertDontSee('192.168.18.124', false);

        $this->assertStringContainsString('url: /api/v1', (string) file_get_contents(base_path('docs/openapi.yaml')));
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
