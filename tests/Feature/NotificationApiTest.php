<?php

namespace Tests\Feature;

use App\Models\ApiClient;
use App\Models\Declaration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Notifications\DeclarationGenerated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Centro de notificaciones de CodeRED Mobile.
 *
 * Se apoya en el canal `database` de Laravel Notifications: aquí se comprueba
 * que el historial es estrictamente personal, que el estado leído/no leído
 * funciona y que una declaración generada produce un aviso sin filtrar datos
 * personales al contenido.
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private const ABILITY = 'mobile';

    private function user(string ...$permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['slug' => 'notif-'.uniqid(), 'name' => 'Tester']);

        foreach ($permissions as $slug) {
            $permission = Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function notify(User $user, int $howMany = 1): void
    {
        for ($i = 0; $i < $howMany; $i++) {
            $user->notify(new DeclarationGenerated(Declaration::query()->create([
                'user_id' => $user->getKey(),
                'agency_id' => null,
                'remitente_dni' => '12345678',
                'remitente_nombre' => 'MARIA FERNANDEZ',
                'destinatario_dni' => '87654321',
                'destinatario_nombre' => 'JUAN PEREZ',
                'sede_destino' => 'LIMA CENTRO',
            ])));
        }
    }

    public function test_sin_autenticacion_responde_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_sin_la_ability_movil_el_token_no_alcanza_el_centro(): void
    {
        Sanctum::actingAs($this->user(), ['declaraciones:gestionar']);

        $this->getJson('/api/v1/notifications')->assertForbidden();
    }

    public function test_un_token_tecnico_no_tiene_notificaciones_propias(): void
    {
        $client = ApiClient::factory()->create();
        $token = $client->createToken('Bridge', [self::ABILITY])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_el_historial_llega_paginado_con_el_contador_de_no_leidas(): void
    {
        $user = $this->user();
        $this->notify($user, 3);

        Sanctum::actingAs($user, [self::ABILITY]);
        $response = $this->getJson('/api/v1/notifications?per_page=2');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
        $this->assertSame(3, $response->json('meta.no_leidas'));
    }

    public function test_cada_usuario_solo_ve_las_suyas(): void
    {
        $ajeno = $this->user();
        $this->notify($ajeno, 2);

        $propio = $this->user();
        $this->notify($propio, 1);

        Sanctum::actingAs($propio, [self::ABILITY]);
        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame(1, $response->json('meta.no_leidas'));
    }

    public function test_el_contador_se_puede_pedir_solo(): void
    {
        $user = $this->user();
        $this->notify($user, 2);

        Sanctum::actingAs($user, [self::ABILITY]);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.no_leidas', 2);
    }

    public function test_marcar_una_como_leida_baja_el_contador(): void
    {
        $user = $this->user();
        $this->notify($user, 2);

        Sanctum::actingAs($user, [self::ABILITY]);
        $id = $user->notifications()->first()->id;

        $this->postJson("/api/v1/notifications/{$id}/read")
            ->assertOk()
            ->assertJsonPath('data.no_leidas', 1);

        $this->assertNotNull($user->notifications()->whereKey($id)->first()->read_at);
    }

    public function test_no_se_puede_marcar_la_notificacion_de_otro(): void
    {
        $ajeno = $this->user();
        $this->notify($ajeno);
        $idAjeno = $ajeno->notifications()->first()->id;

        $propio = $this->user();
        Sanctum::actingAs($propio, [self::ABILITY]);

        // 404 y no 403: confirmar que existe ya sería filtrar información.
        $this->postJson("/api/v1/notifications/{$idAjeno}/read")->assertNotFound();

        $this->assertNull($ajeno->notifications()->whereKey($idAjeno)->first()->read_at);
    }

    public function test_marcar_todas_como_leidas_no_toca_las_de_otro(): void
    {
        $ajeno = $this->user();
        $this->notify($ajeno, 2);

        $propio = $this->user();
        $this->notify($propio, 3);

        Sanctum::actingAs($propio, [self::ABILITY]);

        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.marcadas', 3)
            ->assertJsonPath('data.no_leidas', 0);

        $this->assertSame(0, $propio->unreadNotifications()->count());
        $this->assertSame(2, $ajeno->unreadNotifications()->count());
    }

    public function test_generar_una_declaracion_avisa_a_su_autor(): void
    {
        Notification::fake();

        $user = $this->user('declaracion-jurada.view');
        $agency = Agency::factory()->create(['created_by' => $user->getKey(), 'updated_by' => $user->getKey()]);

        Sanctum::actingAs($user, ['declaraciones:gestionar']);
        $this->postJson('/api/v1/declarations', [
            'remitente_dni' => '12345678',
            'remitente_nombre' => 'MARIA FERNANDEZ',
            'destinatario_dni' => '87654321',
            'destinatario_nombre' => 'JUAN PEREZ',
            'agency_id' => $agency->getKey(),
            'items' => [['cantidad' => '1', 'descripcion' => 'Caja de ropa']],
        ])->assertCreated();

        Notification::assertSentTo($user, DeclarationGenerated::class);
    }

    public function test_el_aviso_no_contiene_documentos_ni_nombres_de_personas(): void
    {
        $user = $this->user();
        $this->notify($user);

        $data = $user->notifications()->first()->data;

        $this->assertSame('declaracion.generada', $data['tipo']);
        $this->assertSame('declaraciones', $data['destino']);
        // Una notificación puede leerse en la pantalla de bloqueo.
        $this->assertStringNotContainsString('12345678', $data['mensaje']);
        $this->assertStringNotContainsString('MARIA FERNANDEZ', $data['mensaje']);
        $this->assertStringNotContainsString('JUAN PEREZ', $data['mensaje']);
        $this->assertStringContainsString('LIMA CENTRO', $data['mensaje']);
    }

    public function test_la_notificacion_apunta_a_la_declaracion_que_la_origino(): void
    {
        $user = $this->user();
        $this->notify($user);

        $data = $user->notifications()->first()->data;

        $this->assertSame(Declaration::query()->latest('id')->first()->getKey(), $data['referencia_id']);
    }
}
