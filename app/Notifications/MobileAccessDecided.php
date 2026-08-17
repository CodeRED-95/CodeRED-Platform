<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\PermissionRequestStatus;
use App\Models\PermissionRequest;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa a quien solicitó un acceso móvil de que ya hay decisión.
 *
 * El motivo de un rechazo puede contener información que no debe leerse en una
 * pantalla de bloqueo —o directamente delante de otra persona—, así que el push
 * dice que hubo decisión y el detalle se lee dentro de la app.
 */
class MobileAccessDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PermissionRequest $request) {}

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
        $acceso = $this->request->accessLabel();
        $aprobada = $this->request->status === PermissionRequestStatus::Approved;

        $mensaje = $aprobada
            ? sprintf('Ya puedes utilizar %s en CodeRED Mobile.', $acceso)
            : sprintf('Tu solicitud de acceso a %s fue rechazada.', $acceso);

        // El motivo sí viaja al historial: ahí lo lee quien ha abierto la app.
        if (! $aprobada && is_string($this->request->rejection_reason) && $this->request->rejection_reason !== '') {
            $mensaje .= ' Motivo: '.$this->request->rejection_reason;
        }

        return MobileNotificationPayload::make(
            tipo: $aprobada ? 'acceso.aprobado' : 'acceso.rechazado',
            titulo: $aprobada ? 'Acceso aprobado' : 'Solicitud revisada',
            mensaje: $mensaje,
            destino: MobileNotificationPayload::DESTINO_NINGUNO,
            referenciaId: $this->request->getKey(),
        );
    }

    /**
     * El push, deliberadamente sin el motivo: se lee en la pantalla de bloqueo.
     */
    public function toFcm(object $notifiable): FcmPush
    {
        $aprobada = $this->request->status === PermissionRequestStatus::Approved;
        $acceso = $this->request->accessLabel();

        return FcmPush::make(
            title: $aprobada ? 'Acceso aprobado' : 'Solicitud revisada',
            body: $aprobada
                ? sprintf('Ya puedes utilizar %s en CodeRED Mobile.', $acceso)
                : sprintf('Tu solicitud de acceso a %s fue rechazada.', $acceso),
            data: [
                'type' => $aprobada ? 'permission_approved' : 'permission_rejected',
                'permission_request_id' => $this->request->getKey(),
                'destino' => MobileNotificationPayload::DESTINO_NINGUNO,
            ],
        );
    }
}
