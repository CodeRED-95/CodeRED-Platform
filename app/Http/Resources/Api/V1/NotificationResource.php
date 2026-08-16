<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use LogicException;

/**
 * Notificación expuesta al centro de notificaciones de CodeRED Mobile.
 *
 * Expone la forma de MobileNotificationPayload con valores por defecto: una
 * notificación antigua, guardada antes de que existiera un campo, se sigue
 * pintando en vez de romper la lista.
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof DatabaseNotification) {
            throw new LogicException('NotificationResource requiere una notificación.');
        }

        $notification = $this->resource;
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'tipo' => $data['tipo'] ?? 'general',
            'titulo' => $data['titulo'] ?? 'Notificación',
            'mensaje' => $data['mensaje'] ?? '',
            'destino' => $data['destino'] ?? 'ninguno',
            'referencia_id' => $data['referencia_id'] ?? null,
            'leida' => $notification->read_at !== null,
            'creada_en' => $notification->created_at?->toIso8601String(),
        ];
    }

    public function with(Request $request): array
    {
        return ['success' => true];
    }
}
