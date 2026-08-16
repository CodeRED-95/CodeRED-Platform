<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\MobileDevice;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Notifications\DeclarationGenerated;
use App\Notifications\FcmPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Mockery;
use Tests\TestCase;

/**
 * Entrega por FCM de las notificaciones de la plataforma.
 *
 * Dos cosas se comprueban aquí por encima del resto: que el push no lleve datos
 * personales —se lee en una pantalla de bloqueo— y que un fallo de entrega
 * nunca tumbe el envío, porque el historial en base de datos es la fuente de
 * verdad y un reintento lo duplicaría.
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** No hay factory de Declaration: se construye igual que en el resto de tests. */
    private function declaracionDe(User $user): Declaration
    {
        return Declaration::query()->create([
            'user_id' => $user->getKey(),
            'agency_id' => null,
            'remitente_dni' => '12345678',
            'remitente_nombre' => 'MARIA FERNANDEZ',
            'destinatario_dni' => '87654321',
            'destinatario_nombre' => 'JUAN PEREZ',
            'sede_destino' => 'LIMA CENTRO',
        ]);
    }

    public function test_la_declaracion_generada_usa_los_canales_de_historial_y_push(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $canales = (new DeclarationGenerated($this->declaracionDe($user)))->via($user);

        $this->assertContains('database', $canales);
        $this->assertContains(FcmChannel::class, $canales);
    }

    /**
     * El texto del push no puede identificar a nadie: lo ve cualquiera que mire
     * el teléfono sin desbloquearlo.
     */
    public function test_el_push_no_contiene_datos_personales(): void
    {
        $user = User::factory()->create(['status' => 'active', 'name' => 'Rosa Melgarejo']);
        $declaration = $this->declaracionDe($user);

        $push = (new DeclarationGenerated($declaration))->toFcm($user);

        $this->assertInstanceOf(FcmPush::class, $push);
        $this->assertSame('Declaración generada', $push->title);
        $this->assertSame('Tu declaración jurada fue generada correctamente.', $push->body);

        $texto = $push->title.' '.$push->body.' '.json_encode($push->data);

        foreach ([$user->name, $user->email, (string) $declaration->remitente_dni, (string) $declaration->remitente_nombre, (string) $declaration->sede_destino] as $dato) {
            if ($dato !== '') {
                $this->assertStringNotContainsString($dato, $texto);
            }
        }
    }

    public function test_el_payload_interno_lleva_el_tipo_y_el_identificador(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $declaration = $this->declaracionDe($user);

        $data = (new DeclarationGenerated($declaration))->toFcm($user)->data;

        $this->assertSame('declaration_created', $data['type']);
        $this->assertSame((string) $declaration->getKey(), $data['declaration_id']);
        $this->assertSame('declaraciones', $data['destino']);

        // FCM sólo admite cadenas en `data`.
        foreach ($data as $valor) {
            $this->assertIsString($valor);
        }
    }

    public function test_envia_a_los_dispositivos_del_usuario_y_no_a_los_de_otro(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        MobileDevice::factory()->withToken('token-del-usuario')->create(['user_id' => $user->getKey()]);
        MobileDevice::factory()->withToken('token-de-otra-persona')->create();

        $enviados = [];

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')
            ->once()
            ->andReturnUsing(function ($mensaje, $tokens) use (&$enviados): MulticastSendReport {
                $enviados = $tokens;

                return MulticastSendReport::withItems([]);
            });

        (new FcmChannel($messaging))->send($user, new DeclarationGenerated($this->declaracionDe($user)));

        $this->assertSame(['token-del-usuario'], $enviados);
    }

    public function test_sin_dispositivos_registrados_no_se_llama_a_firebase(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');

        (new FcmChannel($messaging))->send($user, new DeclarationGenerated($this->declaracionDe($user)));

        $this->addToAssertionCount(1);
    }

    /**
     * Un teléfono desinstalado deja un token muerto. Si no se borra, cada
     * notificación futura reintentaría contra él indefinidamente.
     */
    public function test_un_token_caducado_se_elimina_del_registro(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        MobileDevice::factory()->withToken('token-vivo')->create(['user_id' => $user->getKey()]);
        MobileDevice::factory()->withToken('token-muerto')->create(['user_id' => $user->getKey()]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andReturn(
            MulticastSendReport::withItems([
                SendReport::success(MessageTarget::with(MessageTarget::TOKEN, 'token-vivo'), []),
                SendReport::failure(
                    MessageTarget::with(MessageTarget::TOKEN, 'token-muerto'),
                    NotFound::becauseTokenNotFound('token-muerto'),
                ),
            ])
        );

        (new FcmChannel($messaging))->send($user, new DeclarationGenerated($this->declaracionDe($user)));

        $this->assertDatabaseHas('mobile_devices', ['push_token_hash' => MobileDevice::hashToken('token-vivo')]);
        $this->assertDatabaseMissing('mobile_devices', ['push_token_hash' => MobileDevice::hashToken('token-muerto')]);
    }

    /**
     * Si esto lanzara, el job se reintentaría y el canal `database` escribiría
     * una segunda fila idéntica en el historial del usuario.
     */
    public function test_un_fallo_de_firebase_no_propaga_la_excepcion(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        MobileDevice::factory()->create(['user_id' => $user->getKey()]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('sendMulticast')->once()->andThrow(new \RuntimeException('Firebase caído'));

        (new FcmChannel($messaging))->send($user, new DeclarationGenerated($this->declaracionDe($user)));

        // El dispositivo sigue registrado: un corte de Firebase no es motivo
        // para dar de baja a nadie.
        $this->assertSame(1, MobileDevice::query()->count());
    }

    public function test_con_el_interruptor_apagado_no_se_envia_nada(): void
    {
        config(['push.fcm.enabled' => false]);

        $user = User::factory()->create(['status' => 'active']);
        MobileDevice::factory()->create(['user_id' => $user->getKey()]);

        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldNotReceive('sendMulticast');

        (new FcmChannel($messaging))->send($user, new DeclarationGenerated($this->declaracionDe($user)));

        $this->addToAssertionCount(1);
    }
}
