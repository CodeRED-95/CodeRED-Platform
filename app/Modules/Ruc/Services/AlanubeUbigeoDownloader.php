<?php

namespace App\Modules\Ruc\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

final class AlanubeUbigeoDownloader
{
    public function download(): string
    {
        $config = config('ubigeos.sources.alanube');
        $response = Http::timeout((int) $config['timeout'])
            ->retry((int) $config['retries'], 500)
            ->withHeaders([
                'User-Agent' => 'CodeRED-Platform/1.0',
                'Accept' => 'text/html,application/xhtml+xml',
            ])->get((string) $config['url']);

        try {
            return $response->throw()->body();
        } catch (RequestException $exception) {
            throw new \RuntimeException('No se pudo descargar el catálogo de UBIGEO desde Alanube.', previous: $exception);
        }
    }
}
