<?php

namespace Tests\Feature;

use App\Enums\PermissionRequestStatus;
use App\Models\Permission;
use App\Models\PermissionRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\MobileAccessDecided;
use App\Services\Auth\MobileTokenAbilityResolver;
use App\Services\Permissions\MobileAccessManager;
use App\Services\Permissions\MobileAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Solicitudes de acceso a módulos móviles.
 *
 * El sistema tiene dos mitades y las dos importan: que cualquiera pueda pedir
 * acceso a lo que le corresponde, y que nadie pueda usarlo para conseguir otra
 * cosa. La mayoría de estas pruebas son de lo segundo.
 */
class PermissionRequestTest extends TestCase
{
    use RefreshDatabase;

    private const RUC = MobileAccess::RUC;

    private const DNI = MobileAccess::DNI;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['slug' => 'pr-'.uniqid(), 'name' => 'Tester']);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function solicitud(User $user, string $permission, PermissionRequestStatus $status = PermissionRequestStatus::Pending): PermissionRequest
    {
        return PermissionRequest::query()->create([
            'user_id' => $user->getKey(),
            'permission' => $permission,
            'status' => $status,
            'requested_at' => now(),
        ]);
    }

    // --- Solicitar -----------------------------------------------------------

    public function test_sin_autenticacion_responde_401(): void
    {
        $this->postJson('/api/v1/mobile/permission-requests', ['permission' => self::RUC])
            ->assertUnauthorized();
    }

    public function test_un_usuario_sin_acceso_puede_solicitarlo(): void
    {
        $user = $this->userWith();
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/mobile/permission-requests', [
            'permission' => self::RUC,
            'reason' => 'Necesito consultar RUC para atención en agencia.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.permission', self::RUC)
            ->assertJsonPath('data.acceso', 'Consulta RUC')
            ->assertJsonPath('data.estado', 'pending');

        $this->assertSame(1, PermissionRequest::query()->count());
    }

    /**
     * El corazón de la seguridad: la lista blanca. Sin ella, manipular la
     * petición dejaría a un administrador a un clic de conceder cualquier cosa.
     */
    public function test_no_se_puede_solicitar_un_permiso_fuera_de_la_lista_blanca(): void
    {
        Sanctum::actingAs($this->userWith(), ['mobile']);

        foreach (['users.delete', 'api-tokens.create-for-users', 'super-admin', '*'] as $prohibido) {
            $this->postJson('/api/v1/mobile/permission-requests', ['permission' => $prohibido])
                ->assertStatus(422)
                ->assertJsonValidationErrors('permission');
        }

        $this->assertSame(0, PermissionRequest::query()->count());
    }

    public function test_no_se_duplican_solicitudes_pendientes(): void
    {
        $user = $this->userWith();
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/mobile/permission-requests', ['permission' => self::RUC])->assertCreated();

        $this->postJson('/api/v1/mobile/permission-requests', ['permission' => self::RUC])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Ya tienes una solicitud pendiente para este acceso.');

        $this->assertSame(1, PermissionRequest::query()->count());
    }

    /** Una solicitud pendiente de RUC no bloquea pedir DNI. */
    public function test_se_pueden_solicitar_dos_accesos_distintos(): void
    {
        Sanctum::actingAs($this->userWith(), ['mobile']);

        $this->postJson('/api/v1/mobile/permission-requests', ['permission' => self::RUC])->assertCreated();
        $this->postJson('/api/v1/mobile/permission-requests', ['permission' => self::DNI])->assertCreated();

        $this->assertSame(2, PermissionRequest::query()->count());
    }

    public function test_quien_ya_tiene_el_acceso_no_lo_solicita(): void
    {
        Sanctum::actingAs($this->userWith(self::RUC), ['mobile']);

        $this->postJson('/api/v1/mobile/permission-requests', ['permission' => self::RUC])
            ->assertStatus(409);

        $this->assertSame(0, PermissionRequest::query()->count());
    }

    public function test_el_motivo_no_admite_marcado(): void
    {
        Sanctum::actingAs($this->userWith(), ['mobile']);

        $this->postJson('/api/v1/mobile/permission-requests', [
            'permission' => self::RUC,
            'reason' => '<script>alert(1)</script>Atencion de clientes',
        ])->assertCreated();

        $this->assertSame('alert(1)Atencion de clientes', PermissionRequest::query()->firstOrFail()->reason);
    }

    // --- Mis solicitudes -----------------------------------------------------

    public function test_el_listado_propio_describe_cada_acceso(): void
    {
        $user = $this->userWith(self::DNI);
        $this->solicitud($user, self::RUC);
        Sanctum::actingAs($user, ['mobile']);

        $response = $this->getJson('/api/v1/mobile/permission-requests')->assertOk();

        $accesos = collect($response->json('data'));

        $ruc = $accesos->firstWhere('permission', self::RUC);
        $this->assertFalse($ruc['granted']);
        $this->assertSame('pending', $ruc['request']['estado']);

        $dni = $accesos->firstWhere('permission', self::DNI);
        $this->assertTrue($dni['granted']);
        $this->assertNull($dni['request']);
    }

    /** IDOR: nadie ve lo de otro. */
    public function test_el_listado_no_muestra_solicitudes_ajenas(): void
    {
        $ajeno = $this->userWith();
        $this->solicitud($ajeno, self::RUC);

        Sanctum::actingAs($this->userWith(), ['mobile']);

        $accesos = collect($this->getJson('/api/v1/mobile/permission-requests')->assertOk()->json('data'));

        $this->assertNull($accesos->firstWhere('permission', self::RUC)['request']);
    }

    // --- Bandeja administrativa ---------------------------------------------

    public function test_un_usuario_normal_no_alcanza_la_bandeja(): void
    {
        $solicitante = $this->userWith();
        $this->solicitud($solicitante, self::RUC);

        Sanctum::actingAs($solicitante, ['mobile']);

        $this->getJson('/api/v1/admin/permission-requests')->assertForbidden();
    }

    /** Con la ability pero sin el permiso RBAC: 403. Los dos ejes. */
    public function test_sin_el_permiso_rbac_la_bandeja_responde_403(): void
    {
        Sanctum::actingAs($this->userWith(), ['mobile', 'admin:accesos']);

        $this->getJson('/api/v1/admin/permission-requests')->assertForbidden();
    }

    public function test_el_administrador_lista_las_pendientes(): void
    {
        $solicitante = $this->userWith();
        $this->solicitud($solicitante, self::RUC);
        $this->solicitud($solicitante, self::DNI, PermissionRequestStatus::Approved);

        Sanctum::actingAs($this->userWith('permission-requests.view'), ['mobile', 'admin:accesos']);

        $response = $this->getJson('/api/v1/admin/permission-requests?estado=pending')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('meta.pendientes'));
        $this->assertSame($solicitante->email, $response->json('data.0.usuario.email'));
    }

    // --- Aprobar -------------------------------------------------------------

    public function test_aprobar_otorga_el_permiso_real(): void
    {
        $solicitante = $this->userWith();
        $solicitud = $this->solicitud($solicitante, self::RUC);
        $admin = $this->userWith('permission-requests.view', 'permission-requests.manage');

        $this->assertFalse($solicitante->hasPermission(self::RUC));

        Sanctum::actingAs($admin, ['mobile', 'admin:accesos']);

        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/approve")
            ->assertOk()
            ->assertJsonPath('data.estado', 'approved');

        $this->assertTrue($solicitante->fresh()->hasPermission(self::RUC));

        $fresca = $solicitud->fresh();
        $this->assertSame($admin->getKey(), $fresca->reviewed_by);
        $this->assertNotNull($fresca->reviewed_at);
    }

    /** El acceso llega sin convertir a nadie en administrador. */
    public function test_aprobar_no_cambia_el_rol_principal(): void
    {
        $solicitante = $this->userWith();
        $rolesAntes = $solicitante->roles()->pluck('slug')->all();
        $solicitud = $this->solicitud($solicitante, self::RUC);

        Sanctum::actingAs($this->userWith('permission-requests.manage'), ['mobile', 'admin:accesos']);
        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/approve")->assertOk();

        $rolesDespues = $solicitante->fresh()->roles()->pluck('slug')->all();

        foreach ($rolesAntes as $rol) {
            $this->assertContains($rol, $rolesDespues);
        }

        $this->assertContains('acceso-ruc', $rolesDespues);
        $this->assertNotContains('super-admin', $rolesDespues);
    }

    public function test_un_usuario_sin_privilegios_no_puede_aprobar(): void
    {
        $solicitante = $this->userWith();
        $solicitud = $this->solicitud($solicitante, self::RUC);

        Sanctum::actingAs($this->userWith(), ['mobile', 'admin:accesos']);

        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/approve")->assertForbidden();

        $this->assertSame(PermissionRequestStatus::Pending, $solicitud->fresh()->status);
    }

    /** Nadie resuelve lo suyo, ni con permisos de gestión. */
    public function test_nadie_aprueba_su_propia_solicitud(): void
    {
        $admin = $this->userWith('permission-requests.manage');
        $solicitud = $this->solicitud($admin, self::RUC);

        Sanctum::actingAs($admin, ['mobile', 'admin:accesos']);

        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/approve")
            ->assertStatus(409)
            ->assertJsonPath('message', 'No puedes resolver tu propia solicitud.');

        $this->assertFalse($admin->fresh()->hasPermission(self::RUC));
    }

    /**
     * Dos administradores decidiendo a la vez: el segundo se encuentra la
     * solicitud resuelta y recibe un error explícito, sin efectos dobles.
     */
    public function test_aprobar_dos_veces_no_repite_la_decision(): void
    {
        $solicitante = $this->userWith();
        $solicitud = $this->solicitud($solicitante, self::RUC);

        Sanctum::actingAs($this->userWith('permission-requests.manage'), ['mobile', 'admin:accesos']);

        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/approve")->assertOk();
        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/approve")->assertStatus(409);

        $this->assertSame(PermissionRequestStatus::Approved, $solicitud->fresh()->status);
        $this->assertSame(1, $solicitante->fresh()->roles()->where('slug', 'acceso-ruc')->count());
    }

    // --- Rechazar ------------------------------------------------------------

    public function test_rechazar_guarda_el_motivo_y_no_otorga_nada(): void
    {
        $solicitante = $this->userWith();
        $solicitud = $this->solicitud($solicitante, self::DNI);

        Sanctum::actingAs($this->userWith('permission-requests.manage'), ['mobile', 'admin:accesos']);

        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/reject", [
            'motivo' => 'Su puesto no requiere consultas de identidad.',
        ])
            ->assertOk()
            ->assertJsonPath('data.estado', 'rejected')
            ->assertJsonPath('data.motivo_rechazo', 'Su puesto no requiere consultas de identidad.');

        $this->assertFalse($solicitante->fresh()->hasPermission(self::DNI));
    }

    /** Rechazar no retira permisos que la persona ya tuviera. */
    public function test_rechazar_no_retira_permisos_existentes(): void
    {
        $solicitante = $this->userWith(self::RUC);
        $solicitud = $this->solicitud($solicitante, self::DNI);

        Sanctum::actingAs($this->userWith('permission-requests.manage'), ['mobile', 'admin:accesos']);
        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/reject")->assertOk();

        $this->assertTrue($solicitante->fresh()->hasPermission(self::RUC));
    }

    public function test_una_solicitud_inexistente_responde_404(): void
    {
        Sanctum::actingAs($this->userWith('permission-requests.manage'), ['mobile', 'admin:accesos']);

        $this->postJson('/api/v1/admin/permission-requests/999999/approve')->assertNotFound();
    }

    // --- Gestion directa desde Usuarios --------------------------------------

    public function test_un_administrador_concede_el_acceso_sin_solicitud(): void
    {
        $usuario = $this->userWith();
        Sanctum::actingAs($this->userWith('users.view', 'permission-requests.manage'), ['mobile', 'admin:usuarios']);

        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/grant", [
            'permission' => self::RUC,
        ])->assertOk();

        $this->assertTrue($usuario->fresh()->hasPermission(self::RUC));
    }

    public function test_un_administrador_retira_el_acceso(): void
    {
        $usuario = $this->userWith();
        $admin = $this->userWith('users.view', 'permission-requests.manage');
        Sanctum::actingAs($admin, ['mobile', 'admin:usuarios']);

        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/grant", ['permission' => self::RUC])->assertOk();
        $this->assertTrue($usuario->fresh()->hasPermission(self::RUC));

        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/revoke", ['permission' => self::RUC])->assertOk();
        $this->assertFalse($usuario->fresh()->hasPermission(self::RUC));
    }

    /** Conceder dos veces no es un error ni deja dos roles. */
    public function test_conceder_es_idempotente(): void
    {
        $usuario = $this->userWith();
        Sanctum::actingAs($this->userWith('users.view', 'permission-requests.manage'), ['mobile', 'admin:usuarios']);

        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/grant", ['permission' => self::RUC])->assertOk();
        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/grant", ['permission' => self::RUC])->assertOk();

        $this->assertSame(1, $usuario->fresh()->roles()->where('slug', 'acceso-ruc')->count());
    }

    /** Sin el permiso de gestion no se concede nada, aunque se vea la ficha. */
    public function test_ver_usuarios_no_basta_para_conceder_accesos(): void
    {
        $usuario = $this->userWith();
        Sanctum::actingAs($this->userWith('users.view'), ['mobile', 'admin:usuarios']);

        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/grant", [
            'permission' => self::RUC,
        ])->assertForbidden();

        $this->assertFalse($usuario->fresh()->hasPermission(self::RUC));
    }

    /** Tampoco por aqui se puede escalar a un permiso arbitrario. */
    public function test_no_se_puede_conceder_un_permiso_fuera_de_la_lista(): void
    {
        $usuario = $this->userWith();
        Sanctum::actingAs($this->userWith('users.view', 'permission-requests.manage'), ['mobile', 'admin:usuarios']);

        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/grant", [
            'permission' => 'users.delete',
        ])->assertStatus(422);

        $this->assertFalse($usuario->fresh()->hasPermission('users.delete'));
    }

    /**
     * Retirar el acceso movil no puede desmontar la configuracion de roles de
     * nadie: si el permiso le llega ademas por su rol principal, sigue ahi.
     */
    public function test_retirar_no_quita_lo_que_da_el_rol_principal(): void
    {
        $usuario = $this->userWith(self::RUC);
        Sanctum::actingAs($this->userWith('users.view', 'permission-requests.manage'), ['mobile', 'admin:usuarios']);

        $this->postJson("/api/v1/admin/users/{$usuario->getKey()}/mobile-access/revoke", ['permission' => self::RUC])->assertOk();

        $this->assertTrue($usuario->fresh()->hasPermission(self::RUC));
    }

    // --- Abilities del token --------------------------------------------------

    /**
     * Lo que evita el logout, con un token de verdad y pasando por /me.
     *
     * No se usa Sanctum::actingAs a proposito: inyecta un doble que no se puede
     * persistir, y aqui lo que se comprueba es justo que el token guardado se
     * actualiza.
     */
    public function test_el_token_gana_la_ability_al_refrescar_sin_reiniciar_sesion(): void
    {
        $usuario = $this->userWith();
        $resolver = app(MobileTokenAbilityResolver::class);

        $nuevo = $usuario->createToken('Movil', $resolver->resolve($usuario));
        $this->assertNotContains('ruc:consultar', (array) $nuevo->accessToken->abilities);

        // Un administrador le concede el acceso mientras su sesion sigue viva.
        app(MobileAccessManager::class)->grant($usuario, self::RUC);

        $this->withToken($nuevo->plainTextToken)
            ->getJson('/api/v1/mobile/me')
            ->assertOk();

        $this->assertContains('ruc:consultar', (array) $nuevo->accessToken->fresh()->abilities);
    }

    /** Y en sentido contrario: retirado el permiso, la ability se va sola. */
    public function test_el_token_pierde_la_ability_cuando_se_retira_el_permiso(): void
    {
        $usuario = $this->userWith();
        $resolver = app(MobileTokenAbilityResolver::class);

        app(MobileAccessManager::class)->grant($usuario, self::RUC);
        $usuario = $usuario->fresh();

        $nuevo = $usuario->createToken('Movil', $resolver->resolve($usuario));
        $this->assertContains('ruc:consultar', (array) $nuevo->accessToken->abilities);

        app(MobileAccessManager::class)->revoke($usuario, self::RUC);

        $this->withToken($nuevo->plainTextToken)
            ->getJson('/api/v1/mobile/me')
            ->assertOk();

        $this->assertNotContains('ruc:consultar', (array) $nuevo->accessToken->fresh()->abilities);
    }

    // --- Aviso ----------------------------------------------------------------

    public function test_la_decision_avisa_al_solicitante(): void
    {
        Notification::fake();

        $solicitante = $this->userWith();
        $solicitud = $this->solicitud($solicitante, self::RUC);

        Sanctum::actingAs($this->userWith('permission-requests.manage'), ['mobile', 'admin:accesos']);
        $this->postJson("/api/v1/admin/permission-requests/{$solicitud->getKey()}/approve")->assertOk();

        Notification::assertSentTo($solicitante, MobileAccessDecided::class);
    }

    /**
     * El motivo de un rechazo puede leerse en una pantalla de bloqueo: el push
     * no lo lleva, el historial sí.
     */
    public function test_el_push_de_rechazo_no_lleva_el_motivo(): void
    {
        $solicitante = $this->userWith();
        $solicitud = $this->solicitud($solicitante, self::DNI);
        $solicitud->forceFill([
            'status' => PermissionRequestStatus::Rejected,
            'rejection_reason' => 'Dato reservado del expediente',
        ])->save();

        $aviso = new MobileAccessDecided($solicitud);

        $push = $aviso->toFcm($solicitante);
        $this->assertStringNotContainsString('Dato reservado', $push->title.' '.$push->body.' '.json_encode($push->data));

        $historial = $aviso->toArray($solicitante);
        $this->assertStringContainsString('Dato reservado', (string) $historial['mensaje']);
    }
}
