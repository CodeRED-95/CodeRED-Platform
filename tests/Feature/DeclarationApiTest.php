<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Declaration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeclarationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Los tests corren como root dentro del contenedor: escribir en el disco
        // real dejaría directorios que el worker PHP (usuario www) no puede usar.
        Storage::fake('local');
    }

    private const ABILITY = 'declaraciones:gestionar';

    private const PERMISSION = 'declaracion-jurada.view';

    private function userWithPermission(string ...$permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['slug' => 'tester-'.uniqid(), 'name' => 'Tester']);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function agency(): Agency
    {
        return Agency::factory()->create();
    }

    /** @return array<string, mixed> */
    private function payload(Agency $agency, array $overrides = []): array
    {
        return array_merge([
            'remitente_dni' => '12345678',
            'remitente_nombre' => 'MARIA FERNANDEZ',
            'remitente_telefono' => '987654321',
            'destinatario_dni' => '87654321',
            'destinatario_nombre' => 'JUAN PEREZ',
            'destinatario_telefono' => '912345678',
            'agency_id' => $agency->getKey(),
            'motivo_envio' => 'Traslado de enseres',
            'items' => [
                ['cantidad' => '2', 'descripcion' => 'Cajas de ropa'],
                ['cantidad' => '1', 'descripcion' => 'Televisor'],
            ],
        ], $overrides);
    }

    public function test_crea_una_declaracion_y_persiste_sus_bienes(): void
    {
        $user = $this->userWithPermission(self::PERMISSION);
        $agency = $this->agency();
        Sanctum::actingAs($user, [self::ABILITY]);

        $response = $this->postJson('/api/v1/declarations', $this->payload($agency));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pdf_available', true)
            ->assertJsonPath('data.remitente_dni', '12345678');

        $declaration = Declaration::query()->firstOrFail();

        $this->assertSame($user->getKey(), $declaration->user_id);
        $this->assertSame($agency->getKey(), $declaration->agency_id);
        $this->assertCount(2, $declaration->items);
        $this->assertSame('Cajas de ropa', $declaration->items[0]->descripcion);
        $this->assertSame(0, $declaration->items[0]->position);
    }

    public function test_la_sede_se_congela_desde_el_catalogo_no_desde_el_cliente(): void
    {
        $user = $this->userWithPermission(self::PERMISSION);
        $agency = $this->agency();
        Sanctum::actingAs($user, [self::ABILITY]);

        // El cliente manda una sede falsa: el servidor debe ignorarla.
        $this->postJson('/api/v1/declarations', $this->payload($agency, ['sede_destino' => 'SEDE INVENTADA']))
            ->assertCreated();

        $this->assertSame(trim((string) $agency->name), Declaration::query()->firstOrFail()->sede_destino);
    }

    public function test_el_snapshot_sobrevive_al_renombrado_de_la_agencia(): void
    {
        $user = $this->userWithPermission(self::PERMISSION);
        $agency = $this->agency();
        Sanctum::actingAs($user, [self::ABILITY]);

        $this->postJson('/api/v1/declarations', $this->payload($agency))->assertCreated();
        $original = Declaration::query()->firstOrFail()->sede_destino;

        $agency->update(['name' => 'AGENCIA RENOMBRADA DESPUES']);

        $this->assertSame($original, Declaration::query()->firstOrFail()->fresh()->sede_destino);
    }

    public function test_sin_autenticacion_responde_401(): void
    {
        $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->assertUnauthorized();
    }

    public function test_sin_el_permiso_rbac_responde_403(): void
    {
        $user = $this->userWithPermission('otro.permiso');
        Sanctum::actingAs($user, [self::ABILITY]);

        $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->assertForbidden();
    }

    public function test_sin_la_ability_del_token_responde_403(): void
    {
        $user = $this->userWithPermission(self::PERMISSION);
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->assertForbidden();
    }

    public function test_una_agencia_inexistente_responde_422(): void
    {
        Sanctum::actingAs($this->userWithPermission(self::PERMISSION), [self::ABILITY]);

        $this->postJson('/api/v1/declarations', $this->payload($this->agency(), ['agency_id' => 999999]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('agency_id');
    }

    public function test_un_payload_invalido_responde_422(): void
    {
        Sanctum::actingAs($this->userWithPermission(self::PERMISSION), [self::ABILITY]);
        $agency = $this->agency();

        $this->postJson('/api/v1/declarations', $this->payload($agency, ['remitente_dni' => '123']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('remitente_dni');

        $this->postJson('/api/v1/declarations', $this->payload($agency, ['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_acepta_carne_de_extranjeria_de_nueve_digitos(): void
    {
        Sanctum::actingAs($this->userWithPermission(self::PERMISSION), [self::ABILITY]);

        $this->postJson('/api/v1/declarations', $this->payload($this->agency(), ['remitente_dni' => '123456789']))
            ->assertCreated();
    }

    public function test_el_pdf_se_entrega_como_application_pdf_con_nombre_seguro(): void
    {
        $user = $this->userWithPermission(self::PERMISSION);
        Sanctum::actingAs($user, [self::ABILITY]);

        $id = $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->json('data.id');

        $response = $this->get("/api/v1/declarations/{$id}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('declaracion-jurada-12345678-', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_un_usuario_no_puede_descargar_la_declaracion_de_otro(): void
    {
        $owner = $this->userWithPermission(self::PERMISSION);
        Sanctum::actingAs($owner, [self::ABILITY]);
        $id = $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->json('data.id');

        $intruder = $this->userWithPermission(self::PERMISSION);
        Sanctum::actingAs($intruder, [self::ABILITY]);

        $this->get("/api/v1/declarations/{$id}/pdf")->assertForbidden();
        $this->getJson("/api/v1/declarations/{$id}")->assertForbidden();
    }

    public function test_el_historial_solo_devuelve_las_propias(): void
    {
        $owner = $this->userWithPermission(self::PERMISSION);
        Sanctum::actingAs($owner, [self::ABILITY]);
        $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->assertCreated();

        $other = $this->userWithPermission(self::PERMISSION);
        Sanctum::actingAs($other, [self::ABILITY]);
        $this->postJson('/api/v1/declarations', $this->payload($this->agency(), ['remitente_dni' => '55555555']))->assertCreated();

        $response = $this->getJson('/api/v1/declarations');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('55555555', $response->json('data.0.remitente_dni'));
    }

    public function test_con_permiso_administrativo_se_ven_todas(): void
    {
        $owner = $this->userWithPermission(self::PERMISSION);
        Sanctum::actingAs($owner, [self::ABILITY]);
        $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->assertCreated();

        $admin = $this->userWithPermission(self::PERMISSION, 'declaracion-jurada.manage');
        Sanctum::actingAs($admin, [self::ABILITY]);
        $this->postJson('/api/v1/declarations', $this->payload($this->agency()))->assertCreated();

        $this->assertCount(2, $this->getJson('/api/v1/declarations')->json('data'));
    }

    // --- Delegación de identidad -------------------------------------------
    //
    // El paquete React (packages/shalom-declaracion-jurada) no tiene tokens de
    // usuario: autentica con su token técnico y declara a quién pertenece la
    // acción con X-CodeRED-User-Id. Es la misma API que consume CodeRED Mobile,
    // pero la declaración debe quedar a nombre de la persona, no del servicio.

    public function test_un_cliente_delegante_crea_la_declaracion_a_nombre_del_usuario(): void
    {
        $user = $this->userWithPermission(self::PERMISSION);
        $token = $this->delegatingClient()->createToken('Bridge', [self::ABILITY])->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->getKey()])
            ->postJson('/api/v1/declarations', $this->payload($this->agency()));

        $response->assertCreated();

        $declaration = Declaration::query()->findOrFail($response->json('data.id'));
        $this->assertSame($user->getKey(), $declaration->user_id);
    }

    public function test_sin_la_cabecera_de_delegacion_el_token_tecnico_no_tiene_historial(): void
    {
        $token = $this->delegatingClient()->createToken('Bridge', [self::ABILITY])->plainTextToken;

        // Aquí no hay ningún usuario autenticado —ese es justo el escenario—,
        // así que created_by se fija a mano en vez de heredarlo de la sesión.
        $autor = $this->userWithPermission(self::PERMISSION);
        $agency = Agency::factory()->create([
            'created_by' => $autor->getKey(),
            'updated_by' => $autor->getKey(),
        ]);

        // Un servicio no es una persona: sin usuario delegado no hay nada que
        // consultar ni a quién atribuir la declaración.
        $this->withToken($token)->getJson('/api/v1/declarations')->assertUnauthorized();
        $this->withToken($token)
            ->postJson('/api/v1/declarations', $this->payload($agency))
            ->assertUnauthorized();
    }

    public function test_el_usuario_delegado_sin_permiso_es_rechazado(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $token = $this->delegatingClient()->createToken('Bridge', [self::ABILITY])->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->getKey()])
            ->postJson('/api/v1/declarations', $this->payload($this->agency()))
            ->assertForbidden();
    }

    public function test_la_delegacion_solo_alcanza_el_historial_del_usuario_delegado(): void
    {
        // La declaración ajena se siembra directamente: el guard cachea al
        // usuario de la primera petición autenticada del test, así que una
        // llamada HTTP previa impediría evaluar después el token del cliente.
        $ajeno = $this->userWithPermission(self::PERMISSION);
        Declaration::query()->create([
            'user_id' => $ajeno->getKey(),
            'agency_id' => $this->agency()->getKey(),
            'remitente_dni' => '55555555',
            'remitente_nombre' => 'PERSONA AJENA',
            'destinatario_dni' => '87654321',
            'destinatario_nombre' => 'JUAN PEREZ',
            'sede_destino' => 'LIMA',
        ]);

        $propio = $this->userWithPermission(self::PERMISSION);
        $token = $this->delegatingClient()->createToken('Bridge', [self::ABILITY])->plainTextToken;

        $response = $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $propio->getKey()])
            ->getJson('/api/v1/declarations');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_un_cliente_sin_delegacion_habilitada_no_puede_suplantar(): void
    {
        $user = $this->userWithPermission(self::PERMISSION);
        $client = ApiClient::factory()->create(['can_delegate_users' => false]);
        $token = $client->createToken('Bridge', [self::ABILITY])->plainTextToken;

        $this->withToken($token)
            ->withHeaders(['X-CodeRED-User-Id' => (string) $user->getKey()])
            ->postJson('/api/v1/declarations', $this->payload($this->agency()))
            ->assertForbidden();
    }

    private function delegatingClient(): ApiClient
    {
        return ApiClient::factory()->create([
            'name' => 'Declaración Jurada Shalom',
            'can_delegate_users' => true,
            'delegation_permission' => self::PERMISSION,
        ]);
    }
}
