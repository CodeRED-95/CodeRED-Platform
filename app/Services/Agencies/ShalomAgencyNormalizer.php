<?php

namespace App\Services\Agencies;

final class ShalomAgencyNormalizer
{
    public function normalize(array $row): array
    {
        $sourceRecord = is_array($row['source_record'] ?? null) ? $row['source_record'] : [];
        $schedule = is_array($row['schedule'] ?? null) ? $row['schedule'] : [];
        $classification = is_array($row['classification'] ?? null) ? $row['classification'] : [];
        $geographicIds = is_array($row['geographic_ids'] ?? null) ? $row['geographic_ids'] : [];
        $services = is_array($row['services'] ?? null) ? $row['services'] : [];

        $externalId = $this->firstInteger([
            $row['external_id'] ?? null,
            $sourceRecord['ter_id'] ?? null,
        ]);

        $code = $this->firstText([
            $row['code'] ?? null,
            $sourceRecord['ter_abrebiatura'] ?? null,
        ], uppercase: true);

        $name = $this->firstText([
            $row['name'] ?? null,
            $sourceRecord['lugar_over'] ?? null,
        ], uppercase: true);

        $place = $this->firstText([
            $row['place'] ?? null,
            $sourceRecord['nombre'] ?? null,
        ]);

        $department = $this->firstText([
            $row['department'] ?? null,
            $sourceRecord['departamento'] ?? null,
        ], uppercase: true);

        $province = $this->firstText([
            $row['province'] ?? null,
            $sourceRecord['provincia'] ?? null,
        ], uppercase: true);

        $district = $this->resolveDistrict($row, $sourceRecord, $place);
        $address = $this->firstText([
            $row['address'] ?? null,
            $sourceRecord['direccion'] ?? null,
        ]);

        $latitude = $this->firstFloat([
            $row['latitude'] ?? null,
            $sourceRecord['latitud'] ?? null,
        ]);

        $longitude = $this->firstFloat([
            $row['longitude'] ?? null,
            $sourceRecord['longitud'] ?? null,
        ]);

        $general = $this->firstText([
            $schedule['general'] ?? null,
            $sourceRecord['hora_atencion'] ?? null,
        ], uppercase: true);

        $sunday = $this->firstText([
            $schedule['sunday'] ?? null,
            $sourceRecord['hora_domingo'] ?? null,
        ], uppercase: true);

        $tamano = $this->firstText([
            $classification['category'] ?? null,
            $sourceRecord['ter_categoria'] ?? null,
        ], uppercase: true);

        $sendsCategory = $this->firstText([
            $classification['sends_category'] ?? null,
            $sourceRecord['ter_categoria_envia'] ?? null,
        ], uppercase: true);

        $receivesCategory = $this->firstText([
            $classification['receives_category'] ?? null,
            $sourceRecord['ter_categoria_recibe'] ?? null,
        ], uppercase: true);

        $ubigeoId = $this->firstInteger([
            $geographicIds['ubigeo_id'] ?? null,
            $sourceRecord['ubi_id'] ?? null,
        ]);

        $hasTerrestrial = filled($row['texto_chosen_terrestre'] ?? null)
            || filled($sourceRecord['ter_terrestre'] ?? null)
            || filled($sourceRecord['ter_destino'] ?? null)
            || filled($sourceRecord['ter_origen'] ?? null)
            || $externalId !== null
            || $name !== null;

        $hasAir = filled($row['texto_chosen_aereo'] ?? null)
            || $this->truthy($services['air'] ?? null)
            || $this->truthy($sourceRecord['ter_aereo'] ?? null);

        $textoChosenTerrestre = $hasTerrestrial ? $this->buildChosenText($externalId, $department, $province, $district, $name, 'TERRESTRE') : null;
        $textoChosenAereo = $hasAir ? $this->buildChosenText($externalId, $department, $province, $district, $name, 'AEREO') : null;

        $mapUrl = $latitude !== null && $longitude !== null
            ? "https://www.google.com/maps/dir/?api=1&destination={$latitude},{$longitude}"
            : ($this->firstText([$row['map_url'] ?? null, $row['link_mapa'] ?? null, $sourceRecord['link_mapa'] ?? null]) ?? null);

        return [
            'external_id' => $externalId,
            'code' => $code,
            'name' => $name,
            'place' => $place,
            'department' => $department,
            'province' => $province,
            'district' => $district,
            'address' => $address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'general' => $general,
            'sunday' => $sunday,
            'tamano' => $tamano,
            'sends_category' => $sendsCategory,
            'receives_category' => $receivesCategory,
            'ubigeo_id' => $ubigeoId,
            'texto_chosen_terrestre' => $textoChosenTerrestre,
            'texto_chosen_aereo' => $textoChosenAereo,
            'map_url' => $mapUrl,
            'source' => $row['source'] ?? null,
            'source_record' => $sourceRecord ?: null,
        ];
    }

    private function resolveDistrict(array $row, array $sourceRecord, ?string $place): ?string
    {
        $district = $this->firstText([
            $row['district'] ?? null,
            $row['zone'] ?? null,
            $sourceRecord['zona'] ?? null,
        ], uppercase: true);

        if ($district !== null) {
            return $district;
        }

        if (! is_string($place) || trim($place) === '') {
            return null;
        }

        $segments = array_values(array_filter(array_map('trim', explode('/', $place)), static fn ($value) => $value !== ''));

        return $segments[2] ?? null;
    }

    private function buildChosenText(?int $externalId, ?string $department, ?string $province, ?string $district, ?string $name, string $mode): ?string
    {
        $parts = array_filter([
            $externalId !== null ? (string) $externalId : null,
            $department,
            $province,
            $district,
            $name,
            $mode,
        ], static fn ($value) => $value !== null && $value !== '');

        return count($parts) >= 2 ? implode(' - ', $parts) : null;
    }

    private function firstText(array $values, bool $uppercase = false): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value) && ! is_numeric($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value === '' || strtolower($value) === 'null') {
                continue;
            }

            $value = preg_replace('/\s+/u', ' ', $value);

            return $uppercase ? mb_strtoupper($value) : $value;
        }

        return null;
    }

    private function firstInteger(array $values): ?int
    {
        foreach ($values as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null || strtolower((string) $value) === 'null') {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                return (int) $value;
            }
        }

        return null;
    }

    private function firstFloat(array $values): ?float
    {
        foreach ($values as $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null || strtolower((string) $value) === 'null') {
                continue;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'TRUE', 'yes', 'YES'], true);
    }
}
