<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Forma común de toda notificación que llega a CodeRED Mobile.
 *
 * El cliente no conoce cada tipo: pinta título y mensaje, y usa `destino` para
 * navegar. Añadir una notificación nueva no obliga a publicar una versión de la
 * app siempre que reutilice un destino existente.
 */
final class MobileNotificationPayload
{
    /** Destinos que la app sabe abrir hoy. */
    public const DESTINO_DECLARACIONES = 'declaraciones';

    /** Sin pantalla asociada: la notificación sólo se lee. */
    public const DESTINO_NINGUNO = 'ninguno';

    /**
     * @return array<string, mixed>
     */
    public static function make(
        string $tipo,
        string $titulo,
        string $mensaje,
        string $destino = self::DESTINO_NINGUNO,
        ?int $referenciaId = null,
    ): array {
        return [
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'destino' => $destino,
            'referencia_id' => $referenciaId,
        ];
    }
}
