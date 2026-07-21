<?php

namespace App\Modules\Agencies\Support;

use InvalidArgumentException;

final class AgencyImportPayloadReader
{
    public const SCHEMA_VERSION = 1;

    public function read(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('El JSON debe contener una lista de agencias.');
        }

        if (array_is_list($payload)) {
            return $this->result($payload, 'array legado', null, count($payload));
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $module = $payload['module'] ?? $metadata['module'] ?? null;
        if ($module !== null && $module !== 'agencies') {
            throw new InvalidArgumentException('La copia de seguridad pertenece a otro módulo.');
        }

        $version = $payload['schema_version'] ?? $metadata['schema_version'] ?? null;
        if (is_numeric($version) && (int) $version > self::SCHEMA_VERSION) {
            throw new InvalidArgumentException('La versión de esta copia de seguridad todavía no es compatible.');
        }

        $candidates = [
            'data.agencies' => $payload['data']['agencies'] ?? null,
            'agencies' => $payload['agencies'] ?? null,
            'agencias' => $payload['agencias'] ?? null,
        ];
        foreach ($candidates as $format => $agencies) {
            if (is_array($agencies) && array_is_list($agencies)) {
                if ($format === 'data.agencies') {
                    $agencies = array_map($this->normalizeOfficialAgency(...), $agencies);
                }

                return $this->result($agencies, $format, is_numeric($version) ? (int) $version : null, isset($metadata['record_count']) ? (int) $metadata['record_count'] : null);
            }
        }

        throw new InvalidArgumentException('El archivo JSON no contiene una lista reconocible de agencias.');
    }

    private function result(array $agencies, string $format, ?int $version, ?int $declared): array
    {
        if ($agencies === []) {
            throw new InvalidArgumentException('El archivo no contiene agencias.');
        }

        return ['agencies' => $agencies, 'format' => $format, 'schema_version' => $version, 'declared_count' => $declared];
    }

    private function normalizeOfficialAgency(mixed $agency): mixed
    {
        if (! is_array($agency) || array_is_list($agency)) {
            return $agency;
        }

        return [
            ...$agency,
            'agencia' => $agency['agencia'] ?? $agency['name'] ?? null,
            'departamento' => $agency['departamento'] ?? $agency['department'] ?? null,
            'provincia' => $agency['provincia'] ?? $agency['province'] ?? null,
            'distrito' => $agency['distrito'] ?? $agency['district'] ?? null,
            'direccion' => $agency['direccion'] ?? $agency['address'] ?? null,
            'texto_chosen' => $agency['texto_chosen'] ?? $agency['source_text'] ?? null,
            'link_mapa' => $agency['link_mapa'] ?? $agency['map_url'] ?? null,
            'tamano' => $agency['tamano'] ?? $agency['size'] ?? null,
            'co' => $agency['co'] ?? $agency['is_operations_center'] ?? false,
        ];
    }
}
