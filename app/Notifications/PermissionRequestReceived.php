<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PermissionRequest;
use App\Services\Permissions\MobileAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Avisa a quienes pueden aprobar que ha entrado una solicitud de acceso.
 *
 * Va por el canal `database`, que es el que alimenta el centro de
 * notificaciones: así el aviso queda en CodeRED Platform hasta que alguien lo
 * atiende, en lugar de depender de que la persona estuviera mirando.
 */
class PermissionRequestReceived extends Notification
{
    use Queueable;

    public function __construct(private readonly PermissionRequest $request) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $solicitante = $this->request->user;
        $acceso = MobileAccess::label($this->request->permission);

        return [
            'type' => 'permission_request.received',
            'title' => 'Nueva solicitud de acceso',
            'body' => sprintf(
                '%s solicita acceso a %s.',
                $solicitante?->name ?? 'Un usuario',
                $acceso,
            ),
            'permission_request_id' => $this->request->getKey(),
            'permission' => $this->request->permission,
            'permission_label' => $acceso,
            'requester_id' => $solicitante?->getKey(),
            'requester_name' => $solicitante?->name,
            // Ruta a la que llevar desde el aviso, para no obligar a buscarla.
            'route' => 'admin.permission-requests.index',
            'action_url' => '/admin/security/permission-requests',
        ];
    }
}
