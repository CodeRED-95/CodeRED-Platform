<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Declaration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa al autor de que su declaración jurada ya está emitida.
 *
 * Va en cola (Redis) para no alargar la respuesta del POST: el documento ya se
 * generó y el usuario no debe esperar por el aviso.
 *
 * El canal es `database`; el centro de notificaciones de la app lo lee por
 * GET /api/v1/notifications. Cuando exista push, basta añadir el canal a
 * `via()` — el contenido no cambia.
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
        return ['database'];
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
}
