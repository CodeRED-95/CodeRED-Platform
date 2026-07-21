<?php

namespace App\Modules\Agencies\Support;

use InvalidArgumentException;

final class AgencyImportPayloadReader
{
    public const SUPPORTED_SCHEMA_VERSIONS = [1];

    public function read(mixed $payload): array
    {
        if (! is_array($payload)) {
            throw new InvalidArgumentException('El JSON debe contener una lista de agencias.');
        }

        if (array_is_list($payload)) {
            return $this->result($payload, 'array legado', null, count($payload));
        }

        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        if ($metadata !== []) {
            if (($metadata['application'] ?? null) !== 'CodeRED Platform') {
                throw new InvalidArgumentException('El archivo no fue generado por CodeRED Platform.');
            }
            if (($metadata['type'] ?? null) !== 'agency-backup') {
                throw new InvalidArgumentException('El archivo no corresponde a un respaldo de agencias.');
            }
        }
        $module = $payload['module'] ?? $metadata['module'] ?? null;
        if ($module !== null && $module !== 'agencies') {
            throw new InvalidArgumentException('El archivo no corresponde a un respaldo de agencias.');
        }

        $version = $payload['schema_version'] ?? $metadata['schema_version'] ?? null;
        if ($version !== null && (! is_numeric($version) || ! in_array((int) $version, self::SUPPORTED_SCHEMA_VERSIONS, true))) {
            throw new InvalidArgumentException('La versión '.(string) $version.' del respaldo no es compatible. Versiones soportadas: '.implode(', ', self::SUPPORTED_SCHEMA_VERSIONS).'.');
        }

        if ($metadata !== [] && ! isset($payload['data']['agencies'])) {
            throw new InvalidArgumentException('El respaldo no contiene la colección de agencias.');
        }

        $candidates = [
            'data.agencies' => $payload['data']['agencies'] ?? null,
            'agencies' => $payload['agencies'] ?? null,
            'agencias' => $payload['agencias'] ?? null,
        ];
        foreach ($candidates as $format => $agencies) {
            if (is_array($agencies) && array_is_list($agencies)) {
                if (isset($metadata['record_count']) && (! is_numeric($metadata['record_count']) || (int) $metadata['record_count'] !== count($agencies))) {
                    throw new InvalidArgumentException('El número de agencias del respaldo no coincide con los metadatos del archivo.');
                }
                if ($format === 'data.agencies') {
                    $agencies = array_map($this->normalizeOfficialAgency(...), $agencies);
                }

                return $this->result($agencies, $format, is_numeric($version) ? (int) $version : null, isset($metadata['record_count']) ? (int) $metadata['record_count'] : null);
            }
        }

        throw new InvalidArgumentException('El archivo debe contener un array de agencias o un respaldo válido de CodeRED Platform.');
    }

    private function result(array $agencies, string $format, ?int $version, ?int $declared): array
    {
        if ($agencies === []) {
            throw new InvalidArgumentException('El respaldo no contiene agencias para importar.');
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
            '_backup_record' => true,
            '_backup_id' => $agency['id'] ?? null,
            '_backup_moved_to_id' => $agency['moved_to_agency_id'] ?? null,
            '_backup_deleted_at' => $agency['deleted_at'] ?? null,
            'id' => $agency['external_id'] ?? null,
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
