<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Support\AgencyImportNormalizer;
use App\Modules\Agencies\Support\AgencyImportPayloadReader;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class AgencyImportPreviewService
{
    private AgencyImportPayloadReader $reader;

    public function __construct(?AgencyImportPayloadReader $reader = null)
    {
        $this->reader = $reader ?? new AgencyImportPayloadReader;
    }

    public function payloadFromUrl(string $url, int $limitBytes = 5242880): array
    {
        $this->assertSafeUrl($url);

        $response = Http::timeout(20)->acceptJson()->withHeaders([
            'Accept' => 'application/json',
        ])->get($url);

        $body = $response->body();
        if (strlen($body) > $limitBytes) {
            throw new InvalidArgumentException('El archivo excede el tamaño permitido.');
        }

        return $this->payloadFromJson($body)['agencies'];
    }

    public function payloadFromJson(string $contents): array
    {
        return $this->reader->read(json_decode($contents, true, 512, JSON_THROW_ON_ERROR));
    }

    public function normalizePayload(mixed $payload): array
    {
        return $this->reader->read($payload);
    }

    public function previewFromUrl(string $url, int $limitBytes = 5242880): array
    {
        $decoded = $this->payloadFromUrl($url, $limitBytes);

        $items = [];
        $valid = 0;
        $warnings = 0;
        $invalid = 0;

        foreach (array_slice($decoded, 0, 20) as $row) {
            if (! is_array($row) || array_is_list($row)) {
                $invalid++;

                continue;
            }

            $transformed = AgencyImportNormalizer::transform($row);
            $items[] = $transformed->toArray();
            $valid += $transformed->valid ? 1 : 0;
            $warnings += count($transformed->warnings);
            $invalid += $transformed->valid ? 0 : 1;
        }

        return [
            'total_rows' => count($decoded),
            'valid_rows' => $valid,
            'warning_rows' => $warnings,
            'invalid_rows' => $invalid,
            'preview' => $items,
        ];
    }

    public function transformPayload(array $rows): array
    {
        return array_map(static fn (array $row) => AgencyImportNormalizer::transform($row)->toArray(), $rows);
    }

    private function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            throw new InvalidArgumentException('La URL debe usar HTTPS.');
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($host, ['gist.githubusercontent.com', 'raw.githubusercontent.com'], true)) {
            throw new InvalidArgumentException('Host no permitido.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) && $this->isPrivateIp($host)) {
            throw new InvalidArgumentException('Host no permitido.');
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
