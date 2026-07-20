<?php

namespace App\Services\Dni;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\Dni\Data\DniData;
use App\Domain\Dni\Exceptions\DniProviderUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CurrentDniProvider implements DniProviderInterface
{
    public function find(string $dni): ?DniData
    {
        $url = trim((string) config('dni.api_url'));
        if ($url === '') {
            throw new DniProviderUnavailableException('El proveedor DNI no está configurado.');
        }
        try {
            $request = Http::acceptJson()->timeout((int) config('dni.timeout_seconds'))->connectTimeout((int) config('dni.connect_timeout_seconds'));
            $token = trim((string) config('dni.api_token'));
            if ($token !== '') {
                $request = $request->withToken($token);
            }
            $response = $request->get(rtrim($url, '/').'/'.$dni);
        } catch (ConnectionException $exception) {
            throw new DniProviderUnavailableException('El proveedor DNI no está disponible.', previous: $exception);
        }
        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'json')) {
            throw new DniProviderUnavailableException('El proveedor DNI respondió de forma inesperada.');
        }
        $payload = $response->json('data', $response->json());
        if (! is_array($payload)) {
            throw new DniProviderUnavailableException('El proveedor DNI devolvió datos inválidos.');
        }

        return new DniData($dni, (string) ($payload['nombre_completo'] ?? ''), (string) ($payload['nombres'] ?? ''), (string) ($payload['apellido_paterno'] ?? ''), (string) ($payload['apellido_materno'] ?? ''), isset($payload['fecha_nacimiento']) ? (string) $payload['fecha_nacimiento'] : null, isset($payload['edad']) ? (int) $payload['edad'] : null);
    }
}
