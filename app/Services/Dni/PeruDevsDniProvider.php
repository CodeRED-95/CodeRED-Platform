<?php

namespace App\Services\Dni;

use App\Domain\Dni\Contracts\DniProviderInterface;
use App\Domain\Dni\Data\DniData;
use App\Domain\Dni\Data\DniProviderResult;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class PeruDevsDniProvider implements DniProviderInterface
{
    public function __construct(private readonly DniSettingsService $settings) {}

    public function isEnabled(): bool
    {
        return $this->settings->enabled() && $this->settings->isConfigured();
    }

    public function find(string $dni): DniProviderResult
    {
        if (! $this->isEnabled()) {
            return DniProviderResult::failed('unavailable');
        }

        try {
            $response = Http::acceptJson()
                ->timeout($this->settings->timeoutSeconds())
                ->connectTimeout($this->settings->timeoutSeconds())
                ->retry($this->settings->retryTimes(), 500, throw: false)
                ->get($this->settings->baseUrl(), [
                    'document' => $dni,
                    'key' => $this->settings->apiKey(),
                ]);
        } catch (ConnectionException) {
            return DniProviderResult::failed('unavailable');
        }

        return $this->resultFromResponse($response, $dni);
    }

    public function testConnection(string $dni): array
    {
        $started = hrtime(true);
        $result = $this->find($dni);

        return [
            'ok' => in_array($result->status, ['found', 'not_found'], true),
            'status' => $result->status,
            'status_code' => $result->statusCode,
            'response_time_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
        ];
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
        if (($payload['estado'] ?? null) === false) {
            return DniProviderResult::notFound($status);
        }
        if (($payload['estado'] ?? null) !== true || ! $this->successMessage($payload['mensaje'] ?? null) || ! is_array($payload['resultado'] ?? null)) {
            return DniProviderResult::failed('invalid_response', 502);
        }

        $normalized = $this->normalize($payload['resultado'], $dni);

        return $normalized === null
            ? DniProviderResult::failed('invalid_response', 502)
            : DniProviderResult::found($normalized, $status);
    }

    private function normalize(array $data, string $dni): ?DniData
    {
        $document = trim((string) ($data['id'] ?? ''));
        $names = trim((string) ($data['nombres'] ?? ''));
        $paternal = trim((string) ($data['apellido_paterno'] ?? ''));
        $maternal = trim((string) ($data['apellido_materno'] ?? ''));
        $full = trim((string) ($data['nombre_completo'] ?? ''));

        if ($document !== $dni || $names === '' || $paternal === '' || $maternal === '' || $full === '') {
            return null;
        }

        return new DniData(
            $document,
            $full,
            $names,
            $paternal,
            $maternal,
            $this->nullableString($data['genero'] ?? null),
            $this->normalizeDate($data['fecha_nacimiento'] ?? null),
            $this->nullableString($data['codigo_verificacion'] ?? null),
            $document,
        );
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!d/m/Y', trim($value));

            return $date->format('Y-m-d');
        } catch (Throwable) {
            report(new \RuntimeException('PeruDevs devolvió una fecha de nacimiento inválida.'));

            return null;
        }
    }

    private function successMessage(mixed $message): bool
    {
        return is_string($message) && str_contains(mb_strtolower($message), 'encontr');
    }

    private function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
