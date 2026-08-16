<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Actividad reciente del usuario.
 *
 * Se apoya en api_request_logs, la auditoría que ya escribe AuditApiRequest.
 * Lo que importa aquí: que cada quien vea sólo lo suyo, que un permiso retirado
 * borre su rastro de la lista, y que no se filtre qué documento se consultó.
 */
class MobileActivityTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['slug' => 'act-'.uniqid(), 'name' => 'Tester']);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function logFor(User $user, string $service, int $status = 200): ApiRequestLog
    {
        $token = $user->createToken('Movil', ['mobile'])->accessToken;

        return ApiRequestLog::query()->create([
            'token_id' => $token->id,
            'service' => $service,
            'endpoint' => "/api/v1/{$service}/12345678",
            'method' => 'GET',
            'status_code' => $status,
            'created_at' => now(),
        ]);
    }

    public function test_sin_autenticacion_responde_401(): void
    {
        $this->getJson('/api/v1/activity')->assertUnauthorized();
    }

    public function test_sin_la_ability_movil_responde_403(): void
    {
        Sanctum::actingAs($this->userWith('ruc.view'), ['declaraciones:gestionar']);

        $this->getJson('/api/v1/activity')->assertForbidden();
    }

    public function test_devuelve_la_actividad_propia_con_etiqueta_legible(): void
    {
        $user = $this->userWith('dni-records.view');
        $this->logFor($user, 'dni');

        Sanctum::actingAs($user, ['mobile']);
        $response = $this->getJson('/api/v1/activity');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Consulta DNI', $response->json('data.0.titulo'));
    }

    public function test_no_se_ve_la_actividad_de_otro_usuario(): void
    {
        $ajeno = $this->userWith('dni-records.view');
        $this->logFor($ajeno, 'dni');

        $propio = $this->userWith('dni-records.view');

        Sanctum::actingAs($propio, ['mobile']);

        $this->assertCount(0, $this->getJson('/api/v1/activity')->json('data'));
    }

    public function test_un_permiso_retirado_borra_su_rastro_de_la_lista(): void
    {
        // El usuario consultó DNI, pero hoy sólo conserva el permiso de RUC.
        $user = $this->userWith('ruc.view');
        $this->logFor($user, 'dni');
        $this->logFor($user, 'ruc');

        Sanctum::actingAs($user, ['mobile']);
        $data = $this->getJson('/api/v1/activity')->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('ruc', $data[0]['servicio']);
    }

    public function test_las_llamadas_fallidas_no_son_actividad(): void
    {
        $user = $this->userWith('ruc.view');
        $this->logFor($user, 'ruc', 403);
        $this->logFor($user, 'ruc', 500);

        Sanctum::actingAs($user, ['mobile']);

        $this->assertCount(0, $this->getJson('/api/v1/activity')->json('data'));
    }

    public function test_no_se_expone_el_documento_consultado(): void
    {
        $user = $this->userWith('dni-records.view');
        $this->logFor($user, 'dni');

        Sanctum::actingAs($user, ['mobile']);
        $body = $this->getJson('/api/v1/activity')->getContent();

        // El endpoint auditado lleva el documento; el resumen no debe llevarlo.
        $this->assertStringNotContainsString('12345678', $body);
        $this->assertStringNotContainsString('endpoint', $body);
    }

    public function test_el_limite_esta_acotado(): void
    {
        $user = $this->userWith('ruc.view');

        for ($i = 0; $i < 25; $i++) {
            $this->logFor($user, 'ruc');
        }

        Sanctum::actingAs($user, ['mobile']);

        $this->assertCount(5, $this->getJson('/api/v1/activity')->json('data'));
        $this->assertCount(20, $this->getJson('/api/v1/activity?limit=999')->json('data'));
    }

    public function test_un_usuario_sin_permisos_no_recibe_nada(): void
    {
        $user = $this->userWith();
        $this->logFor($user, 'ruc');

        Sanctum::actingAs($user, ['mobile']);

        $this->assertCount(0, $this->getJson('/api/v1/activity')->json('data'));
    }
}
