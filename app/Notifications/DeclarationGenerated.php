<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Declaration;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa al autor de que su declaración jurada ya está emitida.
 *
 * Va en cola (Redis) para no alargar la respuesta del POST: el documento ya se
 * generó y el usuario no debe esperar por el aviso.
 *
 * Van dos canales. `database` guarda el historial que lee el centro de
 * notificaciones por GET /api/v1/notifications, y es la fuente de verdad.
 * `fcm` es sólo la entrega inmediata: si falla, el aviso sigue estando en la
 * app la próxima vez que se abra.
 */
class DeclarationGenerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Declaration $declaration) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $codigo = sprintf(
            'DJ-%s-%06d',
            $this->declaration->created_at?->format('Y') ?? date('Y'),
            $this->declaration->getKey(),
        );

        // Ni DNI ni nombres: una notificación puede leerse en la pantalla de
        // bloqueo, así que sólo lleva el código del documento y la sede.
        return MobileNotificationPayload::make(
            tipo: 'declaracion.generada',
            titulo: 'Declaración generada',
            mensaje: sprintf('%s para %s ya está disponible.', $codigo, $this->declaration->sede_destino),
            destino: MobileNotificationPayload::DESTINO_DECLARACIONES,
            referenciaId: $this->declaration->getKey(),
        );
    }

    /**
     * El texto del push, deliberadamente más corto que el del historial.
     *
     * Esto se lee en la pantalla de bloqueo, delante de quien tenga el teléfono
     * a la vista: ni código de documento, ni sede, ni nada que identifique a
     * nadie. Quien quiera el detalle abre la app, y allí está.
     */
    public function toFcm(object $notifiable): FcmPush
    {
        return FcmPush::make(
            title: 'Declaración generada',
            body: 'Tu declaración jurada fue generada correctamente.',
            data: [
                'type' => 'declaration_created',
                'declaration_id' => $this->declaration->getKey(),
                // Mismo campo que usa el canal `database`: la app navega con
                // una sola regla, no con un mapa de tipos por duplicado.
                'destino' => MobileNotificationPayload::DESTINO_DECLARACIONES,
            ],
        );
    }
}
