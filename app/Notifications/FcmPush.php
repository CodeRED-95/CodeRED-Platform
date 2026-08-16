<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Lo que se envía por push, que no es lo mismo que se guarda en el historial.
 *
 * Una notificación push se pinta en la pantalla de bloqueo, delante de
 * cualquiera que mire el teléfono. Por eso el texto que viaja aquí es más
 * escueto que el del canal `database`: allí el usuario ya ha desbloqueado y
 * abierto la app, aquí no.
 *
 * `data` es el payload interno que la app usa para navegar. Nunca lleva datos
 * personales: sólo el tipo de evento, el destino y el identificador del recurso.
 */
final class FcmPush
{
    /**
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    /**
     * @param  array<string, scalar|null>  $data
     */
    public static function make(string $title, string $body, array $data = []): self
    {
        // FCM sólo acepta cadenas en `data`; convertirlo aquí evita que cada
        // notificación tenga que acordarse.
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $normalized[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return new self($title, $body, $normalized);
    }
}
