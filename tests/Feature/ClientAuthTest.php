<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClientRefreshToken;
use App\Models\ClientSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Autenticación central de los clientes oficiales.
 *
 * Cubre las cuatro áreas del contrato: entrar, renovar, autorizar por permisos
 * vigentes y seguir aceptando los tokens de API de integración sin cambios.
 */
class ClientAuthTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'contrasena-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        // Los permisos de acceso por aplicación los crea una migración de datos;
        // RefreshDatabase la ejecuta, pero los roles de prueba nacen vacíos.
        foreach (['platform.access', 'mobile.access', 'desktop.access', 'dni-records.view', 'ruc.view'] as $slug) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
        }
    }

    /** @param list<string> $permissions */
    private function userWith(array $permissions, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'password' => Hash::make(self::PASSWORD),
            'status' => $status,
            'is_active' => $status === 'active',
        ]);

        $role = Role::query()->create([
            'name' => 'Rol '.uniqid(),
            'slug' => 'rol-'.uniqid(),
        ]);

        $ids = Permission::query()->whereIn('slug', $permissions)->pluck('id');
        $role->permissions()->sync($ids);
        $user->roles()->sync([$role->id]);

        return $user->fresh();
    }

    /** @return array<string, mixed> */
    private function login(User $user, string $application = 'desktop', ?string $password = null): array
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password ?? self::PASSWORD,
            'application' => $application,
            'device_name' => 'Equipo de prueba',
            'platform' => 'Windows',
        ])->json();
    }

    /**
     * Petición autenticada con un access token concreto.
     *
     * forgetGuards() es imprescindible: en una prueba funcional la aplicación
     * persiste entre peticiones y el guard conserva el usuario que resolvió la
     * primera vez, de modo que un segundo Bearer distinto sería ignorado. En
     * producción cada petición arranca una aplicación nueva y esto no aplica.
     */
    private function asToken(string $accessToken): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$accessToken);
    }

    // ---------------------------------------------------------------- login

    public function test_login_con_credenciales_correctas_abre_una_sesion(): void
    {
        $user = $this->userWith(['desktop.access', 'dni-records.view']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'application' => 'desktop',
            'device_name' => 'PC de Carlos',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'expires_at', 'user', 'roles', 'permissions', 'applications']]);

        $this->assertSame(['desktop'], $response->json('data.applications'));
        $this->assertDatabaseHas('client_sessions', [
            'user_id' => $user->id,
            'application' => 'desktop',
            'device_name' => 'PC de Carlos',
            'revoked_at' => null,
        ]);

        // El access token es de sesión, no de integración.
        $token = PersonalAccessToken::query()->latest('id')->first();
        $this->assertSame('session', $token?->kind);
        $this->assertNotNull($token?->expires_at);
    }

    public function test_login_con_credenciales_incorrectas_es_rechazado(): void
    {
        $user = $this->userWith(['desktop.access']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'otra-cosa',
            'application' => 'desktop',
        ])->assertStatus(422)->assertJsonPath('success', false);

        $this->assertDatabaseCount('client_sessions', 0);
    }

    public function test_usuario_inactivo_no_puede_iniciar_sesion(): void
    {
        $user = $this->userWith(['desktop.access'], status: 'inactive');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'application' => 'desktop',
        ])->assertStatus(403);

        $this->assertDatabaseCount('client_sessions', 0);
    }

    public function test_usuario_sin_acceso_a_la_aplicacion_es_rechazado(): void
    {
        // Tiene Mobile pero no Desktop.
        $user = $this->userWith(['mobile.access']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'application' => 'desktop',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'Tu cuenta no tiene acceso a CodeRED Desktop.');

        // Y con Mobile sí entra: el rechazo es por aplicación, no por cuenta.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
            'application' => 'mobile',
        ])->assertOk();
    }

    // -------------------------------------------------------------- refresh

    public function test_refresh_valido_entrega_credenciales_nuevas(): void
    {
        $user = $this->userWith(['desktop.access', 'dni-records.view']);
        $login = $this->login($user);

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['data']['refresh_token'],
        ]);

        $response->assertOk()->assertJsonPath('success', true);

        // Rotación: el refresh devuelto es otro y el anterior queda consumido.
        $this->assertNotSame($login['data']['refresh_token'], $response->json('data.refresh_token'));
        $this->assertNotSame($login['data']['access_token'], $response->json('data.access_token'));

        $original = ClientRefreshToken::query()
            ->where('token_hash', ClientRefreshToken::hash($login['data']['refresh_token']))
            ->first();

        $this->assertNotNull($original?->used_at);
    }

    public function test_reutilizar_un_refresh_cierra_la_sesion_entera(): void
    {
        $user = $this->userWith(['desktop.access']);
        $login = $this->login($user);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['data']['refresh_token']])->assertOk();

        // Segundo canje del mismo refresh: es robo o clonación.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['data']['refresh_token']])
            ->assertStatus(401);

        $session = ClientSession::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($session->revoked_at);
        $this->assertSame('refresh_reuse', $session->revocation_reason);
    }

    public function test_refresh_revocado_deja_de_servir(): void
    {
        $user = $this->userWith(['desktop.access']);
        $login = $this->login($user);

        $session = ClientSession::query()->where('user_id', $user->id)->firstOrFail();
        app(\App\Services\Auth\ClientSessionManager::class)->revoke($session, null, 'test');

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['data']['refresh_token']])
            ->assertStatus(401);
    }

    public function test_refresh_expirado_deja_de_servir(): void
    {
        $user = $this->userWith(['desktop.access']);
        $login = $this->login($user);

        ClientRefreshToken::query()->update(['expires_at' => now()->subDay()]);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['data']['refresh_token']])
            ->assertStatus(401);
    }

    public function test_refresh_falla_si_se_retira_el_acceso_a_la_aplicacion(): void
    {
        $user = $this->userWith(['desktop.access']);
        $login = $this->login($user);

        // Administración retira el acceso a Desktop.
        $user->roles()->first()->permissions()->detach(
            Permission::query()->where('slug', 'desktop.access')->value('id')
        );

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $login['data']['refresh_token']])
            ->assertStatus(401);
    }

    // ---------------------------------------------------------- permissions

    public function test_permisos_del_usuario_deciden_que_puede_consultar(): void
    {
        $soloDni = $this->userWith(['desktop.access', 'dni-records.view']);
        $token = $this->login($soloDni)['data']['access_token'];

        // RUC no: no tiene ruc.view.
        $this->asToken($token)
            ->getJson('/api/v1/ruc/20512528458')
            ->assertStatus(403);

        // Con ambos permisos, ambos endpoints quedan autorizados.
        $ambos = $this->userWith(['desktop.access', 'dni-records.view', 'ruc.view']);
        $tokenAmbos = $this->login($ambos)['data']['access_token'];

        $autorizado = $this->asToken($tokenAmbos)
            ->getJson('/api/v1/ruc/20512528458');

        $this->assertNotSame(403, $autorizado->status());
    }

    public function test_retirar_un_permiso_corta_el_acceso_sin_renovar_el_token(): void
    {
        $user = $this->userWith(['desktop.access', 'ruc.view']);
        $token = $this->login($user)['data']['access_token'];

        $antes = $this->asToken($token)
            ->getJson('/api/v1/ruc/20512528458');

        $this->assertNotSame(403, $antes->status());

        // Se retira el permiso. El access token sigue siendo el mismo.
        $user->roles()->first()->permissions()->detach(
            Permission::query()->where('slug', 'ruc.view')->value('id')
        );

        $this->asToken($token)
            ->getJson('/api/v1/ruc/20512528458')
            ->assertStatus(403);
    }

    // ------------------------------------------------------------ revocación

    public function test_revocar_la_sesion_invalida_el_access_token_de_inmediato(): void
    {
        $user = $this->userWith(['desktop.access', 'ruc.view']);
        $login = $this->login($user);
        $token = $login['data']['access_token'];

        $this->asToken($token)->getJson('/api/v1/auth/me')->assertOk();

        $session = ClientSession::query()->where('user_id', $user->id)->firstOrFail();
        app(\App\Services\Auth\ClientSessionManager::class)->revoke($session, null, 'admin');

        $this->asToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_desactivar_al_usuario_bloquea_su_sesion(): void
    {
        $user = $this->userWith(['desktop.access']);
        $token = $this->login($user)['data']['access_token'];

        $user->forceFill(['status' => 'inactive', 'is_active' => false])->save();

        $this->asToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_logout_cierra_solo_su_propia_sesion(): void
    {
        $user = $this->userWith(['desktop.access', 'mobile.access']);
        $desktop = $this->login($user, 'desktop');
        $mobile = $this->login($user, 'mobile');

        $this->asToken($desktop['data']['access_token'])
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // La sesión de Mobile sigue viva.
        $this->asToken($mobile['data']['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $this->assertSame(1, ClientSession::query()->active()->where('user_id', $user->id)->count());
    }

    public function test_listar_y_cerrar_sesiones_propias(): void
    {
        $user = $this->userWith(['desktop.access', 'mobile.access']);
        $desktop = $this->login($user, 'desktop');
        $this->login($user, 'mobile');

        $response = $this->asToken($desktop['data']['access_token'])
            ->getJson('/api/v1/auth/sessions')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertTrue(collect($response->json('data'))->firstWhere('application', 'desktop')['current']);

        // Cerrar todas menos la actual.
        $this->asToken($desktop['data']['access_token'])
            ->deleteJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('data.revoked', 1);

        $this->asToken($desktop['data']['access_token'])
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    // --------------------------------------------- compatibilidad API tokens

    public function test_un_token_de_integracion_sigue_funcionando_por_abilities(): void
    {
        $user = $this->userWith([]); // sin ningún permiso RBAC
        $plain = $user->createToken('n8n', ['ruc:consultar'])->plainTextToken;

        // Es de integración: el kind por defecto no cambió.
        $this->assertSame('integration', PersonalAccessToken::query()->latest('id')->value('kind'));

        // Autoriza por ability, no por RBAC: el usuario no tiene ruc.view.
        $porAbility = $this->asToken($plain)
            ->getJson('/api/v1/ruc/20512528458');

        $this->assertNotSame(403, $porAbility->status());

        // Y sigue sin poder hacer aquello para lo que no tiene ability.
        $this->asToken($plain)
            ->getJson('/api/v1/dni/71218478')
            ->assertStatus(403);
    }

    public function test_un_token_de_integracion_no_puede_usar_los_endpoints_de_sesion(): void
    {
        $user = $this->userWith(['desktop.access']);
        $plain = $user->createToken('n8n', ['ruc:consultar'])->plainTextToken;

        // profile:read no está entre sus abilities.
        $this->asToken($plain)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }
}
