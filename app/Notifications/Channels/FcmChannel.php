<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\MobileDevice;
use App\Models\User;
use App\Notifications\FcmPush;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Throwable;

/**
 * Entrega inmediata por Firebase Cloud Messaging.
 *
 * El historial no vive aquí: lo guarda el canal `database`, que es la fuente de
 * verdad. Esto es sólo el aviso que hace sonar el teléfono, y por eso está
 * diseñado para **no fallar nunca hacia afuera**.
 *
 * Esa decisión tiene una razón concreta. Laravel envía todos los canales de una
 * notificación en el mismo job: si este lanzara una excepción, un reintento
 * volvería a ejecutar `database` y el usuario acabaría con dos filas idénticas
 * en su centro de notificaciones. Un push perdido es un incordio; un historial
 * duplicado es un error de datos. Así que cualquier fallo se registra y se
 * traga.
 *
 * Los tokens que Firebase reporta como desconocidos o inválidos se borran en el
 * momento. Sin eso, un teléfono desinstalado seguiría recibiendo intentos para
 * siempre.
 */
class FcmChannel
{
    public function __construct(private readonly Messaging $messaging) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toFcm')) {
            return;
        }

        if (! config('push.fcm.enabled')) {
            return;
        }

        /** @var FcmPush|null $push */
        $push = $notification->toFcm($notifiable);

        if (! $push instanceof FcmPush) {
            return;
        }

        $devices = $notifiable->mobileDevices()->get();

        if ($devices->isEmpty()) {
            return;
        }

        try {
            $this->dispatch($notifiable, $devices, $push);
        } catch (Throwable $exception) {
            // Sin token ni payload en el log: sólo a quién no se le pudo avisar
            // y por qué falló el transporte.
            Log::warning('fcm_send_failed', [
                'user_id' => $notifiable->getKey(),
                'devices' => $devices->count(),
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  Collection<int, MobileDevice>  $devices
     */
    private function dispatch(User $user, $devices, FcmPush $push): void
    {
        $tokens = [];

        foreach ($devices as $device) {
            $token = (string) $device->push_token;

            if ($token !== '') {
                $tokens[$token] = $device;
            }
        }

        if ($tokens === []) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($push->title, $push->body))
            ->withData($push->data)
            // Alta prioridad: un aviso que llega media hora tarde ya no avisa
            // de nada. `default` como canal de Android para que suene con los
            // ajustes que el usuario tenga puestos.
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => ['channel_id' => 'default'],
            ]));

        $report = $this->messaging->sendMulticast($message, array_keys($tokens));

        $caducados = array_merge($report->unknownTokens(), $report->invalidTokens());

        foreach ($caducados as $token) {
            // Se borra por hash: el token no se vuelve a escribir en ningún
            // sitio, ni siquiera en una consulta.
            MobileDevice::query()
                ->where('push_token_hash', MobileDevice::hashToken((string) $token))
                ->delete();
        }

        if ($caducados !== []) {
            Log::info('fcm_tokens_pruned', [
                'user_id' => $user->getKey(),
                'removed' => count($caducados),
            ]);
        }
    }
}
