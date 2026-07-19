<?php

namespace Tests\Feature;

use App\Livewire\Admin\ApiTokens\Index;
use App\Models\ActivityLog;
use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_administrator_can_open_tokens_and_documentation(): void
    {
        $super = $this->superAdmin();
        $viewer = User::factory()->create();

        $this->actingAs($super)->get(route('admin.api-tokens.index'))->assertOk()->assertSee('API y Tokens');
        $this->actingAs($super)->get(route('api.docs'))->assertOk()->assertSee('API CodeRED Platform');
        $this->actingAs($super)->get(route('api.docs.spec'))->assertOk()->assertHeader('content-type', 'application/yaml; charset=UTF-8');
        $this->actingAs($viewer)->get(route('admin.api-tokens.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('api.docs'))->assertForbidden();
        auth()->forgetGuards();
        config()->set('api.docs_require_auth', false);
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
            ->set('expirationDate', now()->addDays(30)->toDateString())
            ->set('abilities', ['agencies:read', 'profile:read'])
            ->call('createToken')
            ->assertHasNoErrors()
            ->assertSet('createdTokenName', 'Extensión Chrome');

        $plain = $component->get('plainTextToken');
        $this->assertIsString($plain);
        $this->assertStringContainsString('|', $plain);
        $token = ApiToken::query()->sole();
        $this->assertNotSame($plain, $token->token);
        $this->assertSame(['agencies:read', 'profile:read'], $token->abilities);
        $this->assertSame('Equipo principal', $token->description);
        $this->assertSame($super->id, $token->created_by);
        $this->assertDatabaseHas('activity_logs', ['action' => 'api_token_created', 'auditable_id' => $token->id]);
        $this->assertStringNotContainsString($plain, json_encode(ActivityLog::query()->latest('id')->first()?->new_values, JSON_THROW_ON_ERROR));

        $component->call('dismissPlainToken')->assertSet('plainTextToken', null);
        Livewire::actingAs($super)->test(Index::class)->assertDontSee($plain);
    }

    public function test_invalid_ability_is_rejected(): void
    {
        $super = $this->superAdmin();

        Livewire::actingAs($super)->test(Index::class)
            ->set('name', 'Peligroso')
            ->set('targetUserId', $super->id)
            ->set('abilities', ['users:manage'])
            ->call('createToken')
            ->assertHasErrors(['abilities.0']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
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
            ->assertSee('Autenticación')
            ->assertSee('Buscar endpoint')
            ->assertSee('codeRedApiDocs', false)
            ->assertSee('autocomplete="off"', false);

        $script = file_get_contents(resource_path('js/api-docs.js'));
        $this->assertIsString($script);
        $this->assertStringContainsString('async mountSwagger()', $script);
        $this->assertStringContainsString('if (this.swagger || !this.$refs.swagger) return;', $script);
        $this->assertStringContainsString('persistAuthorization: false', $script);
        $this->assertStringContainsString('tryItOutEnabled: true', $script);
        $this->assertStringContainsString('Authorization: Bearer TU_TOKEN', $script);
        $this->assertStringNotContainsString('localStorage', $script);
        $this->assertStringNotContainsString('sessionStorage', $script);
        $this->assertStringNotContainsString('innerHTML', $script);
    }

    private function superAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador', 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
