<?php

namespace Tests\Feature;

use App\Enums\ApiTokenRequestStatus;
use App\Models\ApiClient;
use App\Models\ApiToken;
use App\Models\ApiTokenRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\MobileTokenAbilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Área de administración de CodeRED Mobile.
 *
 * Comprueba los dos ejes en todas las rutas —ability del token y permiso RBAC—,
 * que el token emitido se devuelve una única vez, y que ningún listado filtra
 * datos sensibles (valor del token, contraseñas, contactos completos).
 */
class MobileAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['slug' => 'admin-test-'.uniqid(), 'name' => 'Admin de prueba']);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function pendingRequest(): ApiTokenRequest
    {
        return ApiTokenRequest::factory()->create([
            'status' => ApiTokenRequestStatus::Pending,
            'application_name' => 'n8n Producción',
            'requested_at' => now(),
        ]);
    }

    // --- Abilities -----------------------------------------------------------

    public function test_el_resolver_concede_las_abilities_administrativas_solo_con_su_permiso(): void
    {
        $resolver = new MobileTokenAbilityResolver;

        $sinNada = User::factory()->create(['status' => 'active']);
        $this->assertSame(['mobile'], $resolver->resolve($sinNada));

        $soloTokens = $this->userWith('api-tokens.view-any');
        $abilities = $resolver->resolve($soloTokens);
        $this->assertContains('admin:tokens', $abilities);
        $this->assertNotContains('admin:usuarios', $abilities);
        $this->assertNotContains('admin:solicitudes', $abilities);

        $completo = $this->userWith('api-tokens.view-any', 'api-token-requests.view', 'users.view');
        $todas = $resolver->resolve($completo);
        $this->assertContains('admin:tokens', $todas);
        $this->assertContains('admin:solicitudes', $todas);
        $this->assertContains('admin:usuarios', $todas);
    }

    public function test_ninguna_ability_administrativa_es_un_comodin(): void
    {
        $resolver = new MobileTokenAbilityResolver;
        $abilities = $resolver->resolve($this->userWith('api-tokens.view-any', 'api-token-requests.view', 'users.view'));

        $this->assertNotContains('*', $abilities);
    }

    // --- Tokens --------------------------------------------------------------

    public function test_sin_autenticacion_el_area_responde_401(): void
    {
        $this->getJson('/api/v1/admin/tokens')->assertUnauthorized();
        $this->getJson('/api/v1/admin/token-requests')->assertUnauthorized();
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    }

    public function test_sin_la_ability_del_token_responde_403(): void
    {
        // El usuario SÍ tiene el permiso, pero su token no alcanza el área.
        Sanctum::actingAs($this->userWith('api-tokens.view-any'), ['mobile']);

        $this->getJson('/api/v1/admin/tokens')->assertForbidden();
    }

    public function test_con_ability_pero_sin_permiso_rbac_responde_403(): void
    {
        // Token con la ability y usuario sin el permiso: el controlador vuelve a
        // comprobar contra la base, así que no basta con la ability.
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['admin:tokens']);

        $this->getJson('/api/v1/admin/tokens')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tu usuario no tiene permiso para realizar esta acción.');
    }

    public function test_un_token_tecnico_no_puede_administrar(): void
    {
        $client = ApiClient::factory()->create();
        $token = $client->createToken('Bridge', ['admin:tokens'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/admin/tokens')->assertUnauthorized();
    }

    public function test_el_listado_de_tokens_no_expone_ningun_valor_de_token(): void
    {
        $admin = $this->userWith('api-tokens.view-any');
        $creado = $admin->createToken('Token de prueba', ['dni:consultar']);

        Sanctum::actingAs($admin, ['admin:tokens']);
        $response = $this->getJson('/api/v1/admin/tokens');

        $response->assertOk()->assertJsonPath('success', true);
        $body = $response->getContent();

        $this->assertStringNotContainsString($creado->plainTextToken, $body);
        // Tampoco el hash guardado en la columna `token`.
        $this->assertStringNotContainsString($creado->accessToken->token, $body);
        $this->assertStringContainsString('Token de prueba', $body);
    }

    public function test_el_catalogo_de_tipos_trae_las_abilities_de_cada_uno(): void
    {
        Sanctum::actingAs($this->userWith('api-tokens.view-any'), ['admin:tokens']);

        $response = $this->getJson('/api/v1/admin/tokens/types');

        $response->assertOk();
        $tipos = collect($response->json('data'));
        $this->assertTrue($tipos->contains(fn (array $t): bool => $t['valor'] === 'dni' && $t['abilities'] === ['dni:consultar']));
        $this->assertSame(365, $response->json('meta.vigencia_maxima_dias'));
    }

    public function test_crear_un_token_devuelve_el_valor_plano_una_sola_vez(): void
    {
        $admin = $this->userWith('api-tokens.view-any', 'api-tokens.create-for-users');
        $destinatario = $this->userWith('dni-records.view');

        Sanctum::actingAs($admin, ['admin:tokens']);

        $response = $this->postJson('/api/v1/admin/tokens', [
            'nombre' => 'Token n8n',
            'tipo' => 'dni',
            'vigencia_dias' => 30,
            'usuario_id' => $destinatario->getKey(),
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $plano = $response->json('data.token');
        $this->assertNotEmpty($plano);
        $this->assertStringContainsString('Guarda este token ahora', $response->json('data.aviso'));

        // Las abilities las decide el tipo, no el cliente.
        $this->assertSame(['dni:consultar'], $response->json('data.detalle.abilities'));

        // Y al volver a listar ya no aparece por ningún lado.
        $this->assertStringNotContainsString($plano, $this->getJson('/api/v1/admin/tokens')->getContent());
    }

    public function test_crear_un_token_sin_permiso_de_creacion_responde_403(): void
    {
        Sanctum::actingAs($this->userWith('api-tokens.view-any'), ['admin:tokens']);

        $this->postJson('/api/v1/admin/tokens', [
            'nombre' => 'Token n8n',
            'tipo' => 'dni',
            'vigencia_dias' => 30,
            'usuario_id' => User::factory()->create(['status' => 'active'])->getKey(),
        ])->assertForbidden();
    }

    public function test_un_tipo_de_token_inexistente_responde_422(): void
    {
        $admin = $this->userWith('api-tokens.view-any', 'api-tokens.create-for-users');
        Sanctum::actingAs($admin, ['admin:tokens']);

        $this->postJson('/api/v1/admin/tokens', [
            'nombre' => 'Token raro',
            'tipo' => 'acceso-total',
            'vigencia_dias' => 30,
            'usuario_id' => $admin->getKey(),
        ])->assertStatus(422);
    }

    public function test_revocar_un_token_lo_marca_sin_borrarlo(): void
    {
        $admin = $this->userWith('api-tokens.view-any', 'api-tokens.revoke-any');
        $token = $admin->createToken('Para revocar', ['dni:consultar'])->accessToken;

        Sanctum::actingAs($admin, ['admin:tokens']);

        $this->deleteJson("/api/v1/admin/tokens/{$token->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $fila = ApiToken::query()->find($token->id);
        $this->assertNotNull($fila, 'la fila debe seguir existiendo para la auditoría');
        $this->assertNotNull($fila->revoked_at);
    }

    public function test_revocar_sin_permiso_responde_403(): void
    {
        $admin = $this->userWith('api-tokens.view-any');
        $token = $admin->createToken('Intocable', ['dni:consultar'])->accessToken;

        Sanctum::actingAs($admin, ['admin:tokens']);

        $this->deleteJson("/api/v1/admin/tokens/{$token->id}")->assertForbidden();
        $this->assertNull(ApiToken::query()->find($token->id)->revoked_at);
    }

    public function test_revocar_dos_veces_responde_422(): void
    {
        $admin = $this->userWith('api-tokens.view-any', 'api-tokens.revoke-any');
        $token = $admin->createToken('Doble', ['dni:consultar'])->accessToken;

        Sanctum::actingAs($admin, ['admin:tokens']);

        $this->deleteJson("/api/v1/admin/tokens/{$token->id}")->assertOk();
        $this->deleteJson("/api/v1/admin/tokens/{$token->id}")->assertStatus(422);
    }

    // --- Solicitudes ---------------------------------------------------------

    public function test_el_listado_de_solicitudes_filtra_por_estado_y_busca(): void
    {
        $this->pendingRequest();
        ApiTokenRequest::factory()->create([
            'status' => ApiTokenRequestStatus::Rejected,
            'application_name' => 'Integración vieja',
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($this->userWith('api-token-requests.view'), ['admin:solicitudes']);

        $pendientes = $this->getJson('/api/v1/admin/token-requests?estado=pending');
        $pendientes->assertOk();
        $this->assertCount(1, $pendientes->json('data'));
        $this->assertSame('pending', $pendientes->json('data.0.estado'));

        $busqueda = $this->getJson('/api/v1/admin/token-requests?search=n8n');
        $this->assertCount(1, $busqueda->json('data'));
        $this->assertSame('n8n Producción', $busqueda->json('data.0.aplicacion'));
    }

    public function test_la_solicitud_no_expone_el_token_cifrado_ni_contactos_completos(): void
    {
        $solicitud = $this->pendingRequest();

        Sanctum::actingAs($this->userWith('api-token-requests.view'), ['admin:solicitudes']);

        $response = $this->getJson("/api/v1/admin/token-requests/{$solicitud->id}");

        $response->assertOk();
        $body = $response->getContent();

        foreach (['token_ciphertext', 'token_hash', 'token_last_four', 'requester_email_blind_index'] as $campo) {
            $this->assertStringNotContainsString($campo, $body);
        }
    }

    public function test_aprobar_una_solicitud_emite_el_token_en_el_servidor(): void
    {
        Queue::fake();

        $solicitud = $this->pendingRequest();
        $admin = $this->userWith('api-token-requests.view', 'api-token-requests.approve');
        $destinatario = $this->userWith('dni-records.view');

        Sanctum::actingAs($admin, ['admin:solicitudes']);

        $response = $this->postJson("/api/v1/admin/token-requests/{$solicitud->id}/approve", [
            'nombre_token' => 'Token aprobado',
            'tipo_token' => 'dni',
            'vigencia_dias' => 30,
            'usuario_id' => $destinatario->getKey(),
        ]);

        $response->assertOk()->assertJsonPath('data.estado', 'approved');

        $solicitud->refresh();
        $this->assertSame($admin->getKey(), $solicitud->reviewed_by);
        $this->assertNotNull($solicitud->personal_access_token_id);

        // El valor plano jamás viaja en la respuesta de aprobación.
        $this->assertStringNotContainsString('plainTextToken', $response->getContent());
        $this->assertNull($response->json('data.token'));
    }

    public function test_aprobar_sin_permiso_responde_403(): void
    {
        $solicitud = $this->pendingRequest();
        Sanctum::actingAs($this->userWith('api-token-requests.view'), ['admin:solicitudes']);

        $this->postJson("/api/v1/admin/token-requests/{$solicitud->id}/approve", [
            'nombre_token' => 'Token',
            'tipo_token' => 'dni',
            'vigencia_dias' => 30,
            'usuario_id' => User::factory()->create(['status' => 'active'])->getKey(),
        ])->assertForbidden();

        $this->assertSame('pending', $solicitud->fresh()->statusValue());
    }

    public function test_aprobar_dos_veces_responde_422(): void
    {
        Queue::fake();

        $solicitud = $this->pendingRequest();
        $admin = $this->userWith('api-token-requests.view', 'api-token-requests.approve');

        Sanctum::actingAs($admin, ['admin:solicitudes']);

        $payload = [
            'nombre_token' => 'Token aprobado',
            'tipo_token' => 'dni',
            'vigencia_dias' => 30,
            'usuario_id' => $admin->getKey(),
        ];

        $this->postJson("/api/v1/admin/token-requests/{$solicitud->id}/approve", $payload)->assertOk();
        $this->postJson("/api/v1/admin/token-requests/{$solicitud->id}/approve", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'La solicitud ya fue procesada.');
    }

    public function test_rechazar_registra_el_motivo(): void
    {
        Queue::fake();

        $solicitud = $this->pendingRequest();
        $admin = $this->userWith('api-token-requests.view', 'api-token-requests.reject');

        Sanctum::actingAs($admin, ['admin:solicitudes']);

        $this->postJson("/api/v1/admin/token-requests/{$solicitud->id}/reject", [
            'motivo' => 'La aplicación ya dispone de un token vigente.',
        ])->assertOk()->assertJsonPath('data.estado', 'rejected');

        $solicitud->refresh();
        $this->assertSame('La aplicación ya dispone de un token vigente.', $solicitud->rejection_reason);
        $this->assertSame($admin->getKey(), $solicitud->reviewed_by);
    }

    public function test_rechazar_sin_permiso_responde_403(): void
    {
        $solicitud = $this->pendingRequest();
        Sanctum::actingAs($this->userWith('api-token-requests.view'), ['admin:solicitudes']);

        $this->postJson("/api/v1/admin/token-requests/{$solicitud->id}/reject", ['motivo' => 'No'])
            ->assertForbidden();

        $this->assertSame('pending', $solicitud->fresh()->statusValue());
    }

    // --- Usuarios ------------------------------------------------------------

    public function test_el_listado_de_usuarios_pagina_y_busca(): void
    {
        $admin = $this->userWith('users.view');
        User::factory()->create(['name' => 'Rosa Melgarejo', 'status' => 'active']);
        User::factory()->count(3)->create(['status' => 'active']);

        Sanctum::actingAs($admin, ['admin:usuarios']);

        $pagina = $this->getJson('/api/v1/admin/users?per_page=2');
        $pagina->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $pagina->json('data'));
        $this->assertGreaterThan(1, $pagina->json('meta.last_page'));

        $busqueda = $this->getJson('/api/v1/admin/users?search=Melgarejo');
        $this->assertCount(1, $busqueda->json('data'));
        $this->assertSame('Rosa Melgarejo', $busqueda->json('data.0.nombre'));
    }

    public function test_el_usuario_no_expone_contrasena_ni_datos_internos(): void
    {
        $admin = $this->userWith('users.view');
        Sanctum::actingAs($admin, ['admin:usuarios']);

        $response = $this->getJson("/api/v1/admin/users/{$admin->getKey()}");

        $response->assertOk();
        $body = $response->getContent();

        foreach (['password', 'remember_token', 'telegram_chat_id', 'telegram_user_id'] as $campo) {
            $this->assertStringNotContainsString($campo, $body);
        }

        $response->assertJsonPath('data.email', $admin->email);
        $this->assertIsArray($response->json('data.roles'));
    }

    public function test_usuarios_sin_permiso_responde_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['admin:usuarios']);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }

    public function test_un_usuario_inexistente_responde_404(): void
    {
        Sanctum::actingAs($this->userWith('users.view'), ['admin:usuarios']);

        $this->getJson('/api/v1/admin/users/999999')->assertNotFound();
    }
}
