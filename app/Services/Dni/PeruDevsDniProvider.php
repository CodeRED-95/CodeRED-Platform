<?php

namespace App\Services\Dni;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\Dni\Data\DniData;
use App\Domain\Dni\Data\DniProviderResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class PeruDevsDniProvider implements DniProviderInterface
{
    public function __construct(private readonly DniSettingsService $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->enabled() && filled($this->settings->baseUrl()) && $this->settings->hasApiToken();
    }

    public function find(string $dni): DniProviderResult
    {
        if (! $this->isEnabled()) {
            return DniProviderResult::failed('unavailable');
        }
        try {
            $response = Http::withToken((string) $this->settings->apiToken())->acceptJson()->asJson()->timeout($this->settings->timeout())->connectTimeout($this->settings->timeout())->retry($this->settings->retries(), 500, throw: false)->post(rtrim($this->settings->baseUrl(), '/').'/'.ltrim($this->settings->endpointPath(), '/'), ['document' => $dni]);
        } catch (ConnectionException) {
            return DniProviderResult::failed('unavailable');
        }

        return $this->resultFromResponse($response, $dni);
    }

    public function testConnection(string $dni): array
    {
        $started = hrtime(true);
        $result = $this->find($dni);

        return ['ok' => in_array($result->status, ['found', 'not_found'], true), 'status' => $result->status, 'status_code' => $result->statusCode, 'response_time_ms' => (int) round((hrtime(true) - $started) / 1_000_000)];
    }

    private function resultFromResponse(Response $response, string $dni): DniProviderResult
    {
        $status = $response->status();
        if ($status === 404) {
            return DniProviderResult::notFound($status);
        }
        if (in_array($status, [400, 422], true)) {
            return DniProviderResult::failed('invalid_request', $status);
        }
        if (in_array($status, [401, 403], true)) {
            return DniProviderResult::failed('unauthorized', $status);
        }
        if ($status === 429) {
            return DniProviderResult::failed('rate_limited', $status);
        }
        if (! $response->successful()) {
            return DniProviderResult::failed('unavailable', $status);
        }
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            return DniProviderResult::failed('invalid_response', 502);
        }
        try {
            $payload = $response->json();
        } catch (Throwable) {
            return DniProviderResult::failed('invalid_response', 502);
        }
        if (! is_array($payload)) {
            return DniProviderResult::failed('invalid_response', 502);
        }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $normalized = $this->normalize($data, $dni);

        return $normalized === null ? DniProviderResult::failed('invalid_response', 502) : DniProviderResult::found($normalized, $status);
    }

    private function normalize(array $data, string $dni): ?DniData
    {
        $document = (string) ($data['dni'] ?? $data['documento'] ?? $data['numeroDocumento'] ?? $dni);
        $names = trim((string) ($data['nombres'] ?? $data['names'] ?? ''));
        $paternal = trim((string) ($data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? $data['first_last_name'] ?? ''));
        $maternal = trim((string) ($data['apellidoMaterno'] ?? $data['apellido_materno'] ?? $data['second_last_name'] ?? ''));
        $full = trim((string) ($data['nombreCompleto'] ?? $data['nombre_completo'] ?? $data['full_name'] ?? implode(' ', array_filter([$names, $paternal, $maternal]))));
        if ($document !== $dni || $names === '' || $paternal === '' || $full === '') {
            return null;
        }

        return new DniData($dni, $full, $names, $paternal, $maternal, isset($data['fechaNacimiento']) ? (string) $data['fechaNacimiento'] : (isset($data['fecha_nacimiento']) ? (string) $data['fecha_nacimiento'] : null), isset($data['edad']) ? (int) $data['edad'] : null, isset($data['id']) ? (string) $data['id'] : null);
    }
}
