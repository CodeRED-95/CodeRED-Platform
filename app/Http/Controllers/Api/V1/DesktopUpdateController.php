<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Canal de actualización de CodeRED Desktop.
 *
 * Público a propósito: la aplicación debe poder comprobar si hay una versión
 * nueva antes de tener credencial alguna, y quien la usa con un token de API
 * tampoco tiene sesión. No expone nada sensible: versión, enlace y hash.
 *
 * El manifiesto lo escribe el script de publicación junto al artefacto, y el
 * artefacto lo sirve nginx desde el enlace público de storage. Aquí sólo se
 * lee, se valida y se convierte la ruta relativa en una URL absoluta, para que
 * el cliente no tenga que componerla ni conocer la estructura de carpetas.
 */
class DesktopUpdateController
{
    /** Ruta del manifiesto dentro del disco público. */
    private const MANIFEST = 'releases/desktop/manifest.json';

    public function __invoke(): JsonResponse
    {
        $disk = Storage::disk('public');

        if (! $disk->exists(self::MANIFEST)) {
            // Sin publicaciones todavía no es un error: la aplicación pregunta
            // en cada arranque y debe entender un "no hay nada" sin ruido.
            return $this->respond(null);
        }

        $manifest = json_decode((string) $disk->get(self::MANIFEST), true);

        if (! is_array($manifest) || ! isset($manifest['version'], $manifest['file'], $manifest['sha256'])) {
            return $this->respond(null);
        }

        $relativo = 'releases/desktop/'.basename((string) $manifest['file']);

        if (! $disk->exists($relativo)) {
            // El manifiesto apunta a un archivo que no está: mejor decir que no
            // hay actualización que mandar al cliente a una descarga rota.
            return $this->respond(null);
        }

        return $this->respond([
            'version' => (string) $manifest['version'],
            'url' => rtrim((string) config('app.url'), '/').'/storage/'.$relativo,
            'sha256' => mb_strtolower((string) $manifest['sha256']),
            'size' => $disk->size($relativo),
            'released_at' => $manifest['released_at'] ?? null,
            'notes' => $manifest['notes'] ?? null,
            'minimum_version' => $manifest['minimum_version'] ?? null,
        ]);
    }

    /** @param array<string, mixed>|null $release */
    private function respond(?array $release): JsonResponse
    {
        return response()
            ->json(['success' => true, 'data' => ['release' => $release]])
            // Cinco minutos: suficiente para no castigar al servidor cuando
            // muchos clientes arrancan a la vez, y poco para que una
            // publicación llegue pronto.
            ->header('Cache-Control', 'public, max-age=300')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
