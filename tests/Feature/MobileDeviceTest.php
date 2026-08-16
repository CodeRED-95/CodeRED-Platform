<?php

namespace Tests\Feature;

use App\Models\ApiRequestLog;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Registro y baja del dispositivo que recibe notificaciones push.
 *
 * Lo que se protege aquí es concreto: que el token no salga nunca de la base,
 * que nadie toque el dispositivo de otro, y que un teléfono que cambia de manos
 * deje de avisar al dueño anterior.
 */
class MobileDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'fWzYc-tokendepruebaparaelregistrodeldispositivo-0123456789';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'push_token' => self::TOKEN,
            'platform' => 'android',
            'device_name' => 'Pixel 7',
            'app_version' => '0.14.0',
        ], $overrides);
    }

    public function test_sin_autenticacion_responde_401(): void
    {
        $this->postJson('/api/v1/mobile/devices', $this->payload())->assertUnauthorized();
    }

    public function test_sin_la_ability_movil_responde_403(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/mobile/devices', $this->payload())->assertForbidden();
    }

    public function test_registra_el_dispositivo_y_guarda_el_token_cifrado(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/mobile/devices', $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.device_name', 'Pixel 7');

        $device = MobileDevice::query()->firstOrFail();
        $this->assertSame($user->getKey(), $device->user_id);
        $this->assertSame(self::TOKEN, $device->push_token);
        $this->assertSame(hash('sha256', self::TOKEN), $device->push_token_hash);

        // El valor en bruto de la columna no es el token: el cast lo cifra.
        $crudo = (string) DB::table('mobile_devices')->where('id', $device->getKey())->value('push_token');
        $this->assertNotSame(self::TOKEN, $crudo);
        $this->assertStringNotContainsString(self::TOKEN, $crudo);
    }

    public function test_la_respuesta_nunca_devuelve_el_token(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);

        $response = $this->postJson('/api/v1/mobile/devices', $this->payload());

        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
        $response->assertJsonMissingPath('data.push_token');
        $response->assertJsonMissingPath('data.push_token_hash');
    }

    /**
     * La app reenvía su token en cada arranque. Eso no puede ir dejando filas.
     */
    public function test_registrar_dos_veces_el_mismo_token_no_duplica_el_dispositivo(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user, ['mobile']);

        $this->postJson('/api/v1/mobile/devices', $this->payload())->assertCreated();
        $this->postJson('/api/v1/mobile/devices', $this->payload(['app_version' => '0.15.0']))->assertOk();

        $this->assertSame(1, MobileDevice::query()->count());
        $this->assertSame('0.15.0', MobileDevice::query()->firstOrFail()->app_version);
    }

    /**
     * Un teléfono donde inicia sesión otra persona. El token cambia de dueño en
     * lugar de duplicarse: si no, el usuario anterior seguiría recibiendo sus
     * notificaciones en un aparato que ya no usa.
     */
    public function test_un_token_que_reaparece_con_otro_usuario_cambia_de_dueno(): void
    {
        $anterior = User::factory()->create(['status' => 'active']);
        MobileDevice::factory()->withToken(self::TOKEN)->create(['user_id' => $anterior->getKey()]);

        $nuevo = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($nuevo, ['mobile']);

        $this->postJson('/api/v1/mobile/devices', $this->payload())->assertOk();

        $this->assertSame(1, MobileDevice::query()->count());
        $this->assertSame($nuevo->getKey(), MobileDevice::query()->firstOrFail()->user_id);
        $this->assertSame(0, $anterior->mobileDevices()->count());
    }

    public function test_da_de_baja_su_propio_dispositivo(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $device = MobileDevice::factory()->create(['user_id' => $user->getKey()]);
        Sanctum::actingAs($user, ['mobile']);

        $this->deleteJson('/api/v1/mobile/devices/'.$device->getKey())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(0, MobileDevice::query()->count());
    }

    /**
     * IDOR: el identificador de un dispositivo ajeno no da acceso a nada, y el
     * 404 es el mismo que si no existiera — no confirma su existencia.
     */
    public function test_un_usuario_no_puede_dar_de_baja_el_dispositivo_de_otro(): void
    {
        $ajeno = MobileDevice::factory()->create();
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);

        $this->deleteJson('/api/v1/mobile/devices/'.$ajeno->getKey())->assertNotFound();
        $this->deleteJson('/api/v1/mobile/devices/999999')->assertNotFound();

        $this->assertSame(1, MobileDevice::query()->count());
        $this->assertDatabaseHas('mobile_devices', ['id' => $ajeno->getKey()]);
    }

    public function test_rechaza_una_plataforma_no_soportada(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);

        $this->postJson('/api/v1/mobile/devices', $this->payload(['platform' => 'windows']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('platform');
    }

    public function test_rechaza_el_registro_sin_token(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);

        $this->postJson('/api/v1/mobile/devices', $this->payload(['push_token' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('push_token');
    }

    /**
     * La auditoría de la API guarda endpoint, método y estado, nunca el cuerpo.
     * Esta prueba lo fija: si alguien añadiera el body al log, el token de push
     * acabaría almacenado en claro.
     */
    public function test_la_auditoria_no_almacena_el_token_en_ninguna_columna(): void
    {
        Sanctum::actingAs(User::factory()->create(['status' => 'active']), ['mobile']);

        $this->postJson('/api/v1/mobile/devices', $this->payload())->assertCreated();

        foreach (ApiRequestLog::query()->get() as $log) {
            $this->assertStringNotContainsString(self::TOKEN, (string) json_encode($log->getAttributes()));
        }
    }
}
