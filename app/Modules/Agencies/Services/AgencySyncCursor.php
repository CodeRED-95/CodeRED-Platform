<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Exceptions\InvalidAgencySyncCursorException;

final class AgencySyncCursor
{
    public function encode(int $sequence): string
    {
        $payload = $this->base64UrlEncode(json_encode([
            'v' => 1,
            'sequence' => max(0, $sequence),
            'schema_version' => (int) config('api.agency_schema_version'),
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->signature($payload);
    }

    public function decode(string $cursor): int
    {
        $parts = explode('.', $cursor, 2);
        if (count($parts) !== 2 || ! hash_equals($this->signature($parts[0]), $parts[1])) {
            throw new InvalidAgencySyncCursorException('El cursor no es válido.');
        }

        $decoded = $this->base64UrlDecode($parts[0]);
        try {
            $payload = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidAgencySyncCursorException('El cursor no es válido.');
        }
        if (! is_array($payload)
            || ($payload['v'] ?? null) !== 1
            || ! is_int($payload['sequence'] ?? null)
            || ($payload['sequence'] ?? -1) < 0
            || ($payload['schema_version'] ?? null) !== (int) config('api.agency_schema_version')) {
            throw new InvalidAgencySyncCursorException('El cursor pertenece a otra versión del catálogo.');
        }

        return $payload['sequence'];
    }

    private function signature(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, (string) config('app.key'), true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new InvalidAgencySyncCursorException('El cursor no es válido.');
        }

        return $decoded;
    }
}
