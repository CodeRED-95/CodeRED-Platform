<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Settings\ExtensionBlocking;
use App\Models\Role;
use App\Models\User;
use App\Modules\ExtensionControl\Models\ExtensionBlockRule;
use App\Modules\ExtensionControl\Services\ExtensionBlockRuleService;
use App\Modules\ExtensionControl\Support\BlockingAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bloqueo horario de la extension: panel de administracion y contrato del
 * endpoint que consumen todas las instalaciones conectadas por token.
 */
class ExtensionBlockRulesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * La migracion siembra la regla heredada de Service Order para no dejar
     * produccion sin bloqueo; las pruebas parten de cero para poder afirmar
     * sobre conteos exactos.
     */
    protected function setUp(): void
    {
        parent::setUp();
        ExtensionBlockRule::query()->delete();
        app(ExtensionBlockRuleService::class)->forgetCache();
    }

    public function test_el_endpoint_exige_token(): void
    {
        $this->getJson('/api/v1/extension/chrome/block-rules')->assertUnauthorized();
    }

    public function test_cualquier_token_valido_recibe_las_reglas_activas(): void
    {
        $rule = $this->createRule();

        ExtensionBlockRule::query()->create([
            'label' => 'Inactiva',
            'host_pattern' => 'otro.shalomcontrol.com',
            'path_pattern' => '/*',
            'window_mode' => 'allowed',
            'timezone' => 'America/Lima',
            'is_active' => false,
        ]);

        $response = $this->withHeaders($this->tokenHeaders())
            ->getJson('/api/v1/extension/chrome/block-rules')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rules.0.host_pattern', 'sysnewos.shalomcontrol.com')
            ->assertJsonPath('data.rules.0.window_mode', 'allowed')
            ->assertJsonCount(7, 'data.rules.0.windows');

        // Solo la regla activa viaja al navegador.
        $this->assertCount(1, $response->json('data.rules'));
        $this->assertNotEmpty($response->json('data.version'));
        $this->assertSame('08:00', $response->json('data.rules.0.windows.0.start_time'));
        $this->assertSame($rule->label, $response->json('data.rules.0.label'));
    }

    public function test_la_version_cambia_cuando_cambia_el_horario(): void
    {
        $rule = $this->createRule();
        $first = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/extension/chrome/block-rules')->json('data.version');

        $rule->windows()->where('day_of_week', 0)->update(['end_time' => '17:05:00']);
        app(ExtensionBlockRuleService::class)->forgetCache();

        $second = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/extension/chrome/block-rules')->json('data.version');

        $this->assertNotSame($first, $second);
    }

    public function test_el_admin_configura_lunes_a_sabado_y_domingo_por_separado(): void
    {
        $schedule = [];
        foreach (range(0, 6) as $day) {
            $schedule[$day] = ['enabled' => false, 'start' => '08:00', 'end' => '20:05'];
        }

        Livewire::actingAs($this->admin())
            ->test(ExtensionBlocking::class)
            ->call('create')
            ->set('label', 'Service Order')
            ->set('hostPatterns', 'sysnewos.shalomcontrol.com')
            ->set('pathPattern', '/service-order')
            ->set('schedule', $schedule)
            ->set('bulkStart', '08:00')
            ->set('bulkEnd', '20:05')
            ->call('applyRange', 'monday-saturday')
            ->set('bulkStart', '08:00')
            ->set('bulkEnd', '17:05')
            ->call('applyRange', 'sunday')
            ->call('save')
            ->assertHasNoErrors();

        $rule = ExtensionBlockRule::query()->with('windows')->firstOrFail();

        $this->assertCount(7, $rule->windows);
        $this->assertSame('20:05:00', substr((string) $rule->windows->firstWhere('day_of_week', 1)->end_time, 0, 8));
        $this->assertSame('17:05:00', substr((string) $rule->windows->firstWhere('day_of_week', 0)->end_time, 0, 8));
    }

    public function test_una_regla_admite_varios_dominios(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ExtensionBlocking::class)
            ->call('create')
            ->set('label', 'Service Order')
            ->set('hostPatterns', "sysnewos.shalomcontrol.com
sysprovincia2.shalomcontrol.com
sysnewos.shalomcontrol.com")
            ->set('pathPattern', '/service-order')
            ->call('applyRange', 'all')
            ->call('save')
            ->assertHasNoErrors();

        $rule = ExtensionBlockRule::query()->with('hosts')->firstOrFail();

        // El duplicado se descarta y el primero queda tambien en host_pattern,
        // que es lo unico que entiende la extension 2.4.0.
        $this->assertSame(['sysnewos.shalomcontrol.com', 'sysprovincia2.shalomcontrol.com'], $rule->hostPatterns());
        $this->assertSame('sysnewos.shalomcontrol.com', $rule->host_pattern);

        $data = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/extension/chrome/block-rules')->json('data');

        $this->assertSame(['sysnewos.shalomcontrol.com', 'sysprovincia2.shalomcontrol.com'], $data['rules'][0]['host_patterns']);
        $this->assertCount(2, $data['rules'][0]['destinations']);
        $this->assertSame('sysnewos.shalomcontrol.com', $data['rules'][0]['host_pattern']);
    }

    public function test_cada_destino_puede_traer_su_propia_ruta(): void
    {
        // El caso que fallaba en produccion: se pegaba la URL completa de un
        // segundo dominio y la ruta se perdia, asi que esa pagina no se
        // bloqueaba nunca.
        Livewire::actingAs($this->admin())
            ->test(ExtensionBlocking::class)
            ->call('create')
            ->set('label', 'Service Order')
            ->set('hostPatterns', "sysnewos.shalomcontrol.com
https://sysprovincia2.shalomcontrol.com/ordenservicio/listar?x=1")
            ->set('pathPattern', '/service-order')
            ->call('applyRange', 'all')
            ->call('save')
            ->assertHasNoErrors();

        $rule = ExtensionBlockRule::query()->with('hosts')->firstOrFail();

        $this->assertSame([
            ['host_pattern' => 'sysnewos.shalomcontrol.com', 'path_pattern' => '/service-order'],
            ['host_pattern' => 'sysprovincia2.shalomcontrol.com', 'path_pattern' => '/ordenservicio/listar'],
        ], $rule->destinations());

        $data = $this->withHeaders($this->tokenHeaders())->getJson('/api/v1/extension/chrome/block-rules')->json('data');

        $this->assertSame('/ordenservicio/listar', $data['rules'][0]['destinations'][1]['path_pattern']);
    }

    public function test_el_mismo_dominio_admite_dos_rutas_distintas(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ExtensionBlocking::class)
            ->call('create')
            ->set('label', 'Dos rutas')
            ->set('hostPatterns', "sysprovincia2.shalomcontrol.com/service-order
sysprovincia2.shalomcontrol.com/ordenservicio/listar")
            ->call('applyRange', 'all')
            ->call('save')
            ->assertHasNoErrors();

        $rule = ExtensionBlockRule::query()->with('hosts')->firstOrFail();

        $this->assertCount(2, $rule->destinations());
    }

    public function test_rechaza_un_dominio_invalido_aunque_los_demas_sean_validos(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ExtensionBlocking::class)
            ->call('create')
            ->set('label', 'Mixta')
            ->set('hostPatterns', "sysnewos.shalomcontrol.com
www.youtube.com")
            ->call('applyRange', 'all')
            ->call('save')
            ->assertHasErrors('hostPatterns');

        $this->assertSame(0, ExtensionBlockRule::query()->count());
    }

    public function test_rechaza_dominios_fuera_de_shalomcontrol(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ExtensionBlocking::class)
            ->call('create')
            ->set('label', 'Distracciones')
            ->set('hostPatterns', 'www.youtube.com')
            ->set('pathPattern', '/*')
            ->call('applyRange', 'all')
            ->call('save')
            ->assertHasErrors('hostPatterns');

        $this->assertSame(0, ExtensionBlockRule::query()->count());
    }

    public function test_rechaza_un_horario_sin_dias_activos(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ExtensionBlocking::class)
            ->call('create')
            ->set('label', 'Sin dias')
            ->set('hostPatterns', 'sysnewos.shalomcontrol.com')
            ->call('save')
            ->assertHasErrors('schedule');
    }

    public function test_un_usuario_sin_permiso_no_entra_al_panel(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        Livewire::actingAs($user)
            ->test(ExtensionBlocking::class)
            ->assertForbidden();
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $role = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Administrador']);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh();
    }

    private function createRule(): ExtensionBlockRule
    {
        $rule = ExtensionBlockRule::query()->create([
            'label' => 'Service Order',
            'host_pattern' => 'sysnewos.shalomcontrol.com',
            'path_pattern' => '/service-order',
            'window_mode' => 'allowed',
            'timezone' => 'America/Lima',
            'is_active' => true,
        ]);

        $rule->windows()->createMany(
            collect(range(0, 6))
                ->map(fn (int $day): array => ['day_of_week' => $day, 'start_time' => '08:00:00', 'end_time' => '20:05:00'])
                ->all()
        );

        app(ExtensionBlockRuleService::class)->forgetCache();

        return $rule->fresh('windows');
    }

    /**
     * @param  array<int, string>  $abilities
     * @return array<string, string>
     */
    public function test_un_token_sin_la_ability_no_recibe_reglas(): void
    {
        $this->createRule();

        // Un token de agencias corriente: sirve para buscar, no para bloquear.
        $this->withHeaders($this->tokenHeaders(['agencies:read', 'profile:read']))
            ->getJson('/api/v1/extension/chrome/block-rules')
            ->assertForbidden();
    }

    public function test_el_admin_concede_y_retira_el_control_horario_en_un_token(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $token = $owner->createToken('Extension', ['agencies:read', 'profile:read'])->accessToken;

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\ApiTokens\Index::class)
            ->call('toggleBlockingAbility', $token->getKey());

        $this->assertContains(BlockingAbility::NAME, $token->fresh()->abilities);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Admin\ApiTokens\Index::class)
            ->call('toggleBlockingAbility', $token->getKey());

        // Se retira sin tocar las demas abilities ni invalidar el token.
        $this->assertNotContains(BlockingAbility::NAME, $token->fresh()->abilities);
        $this->assertContains('agencies:read', $token->fresh()->abilities);
    }

    private function tokenHeaders(array $abilities = ['agencies:read', 'extension:blocking', 'profile:read']): array
    {
        $token = User::factory()->create(['status' => 'active'])->createToken('Prueba bloqueo', $abilities)->plainTextToken;

        return ['Authorization' => 'Bearer '.$token];
    }
}
