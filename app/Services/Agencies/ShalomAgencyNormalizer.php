<?php

namespace App\Services\Agencies;

use App\Services\Ubigeos\UbigeoResolver;

final class ShalomAgencyNormalizer
{
    public function normalize(array $row): array
    {
        $sourceRecord = is_array($row['source_record'] ?? null) ? $row['source_record'] : [];
        $schedule = is_array($row['schedule'] ?? null) ? $row['schedule'] : [];
        $classification = is_array($row['classification'] ?? null) ? $row['classification'] : [];
        $geographicIds = is_array($row['geographic_ids'] ?? null) ? $row['geographic_ids'] : [];
        $services = is_array($row['services'] ?? null) ? $row['services'] : [];

        $externalId = $this->firstInteger([$row['external_id'] ?? null, $sourceRecord['ter_id'] ?? null]);
        $code = $this->firstText([$row['code'] ?? null, $sourceRecord['ter_abrebiatura'] ?? null], uppercase: true);
        $name = $this->firstText([$row['name'] ?? null, $sourceRecord['lugar_over'] ?? null], uppercase: true);
        $place = $this->firstText([$row['place'] ?? null, $sourceRecord['nombre'] ?? null]);
        $address = $this->firstText([$row['address'] ?? null, $sourceRecord['direccion'] ?? null]);

        $ubigeoCode = $this->normalizeUbigeoCode($this->firstFilled(
            data_get($row, 'ubigeo_id'),
            data_get($geographicIds, 'ubigeo_id'),
            data_get($sourceRecord, 'ubi_id'),
            data_get($row, 'source_record.ubi_id')
        ));
        $ubigeo = app(UbigeoResolver::class)->findByCode($ubigeoCode);

        $department = $this->firstFilled(
            $this->normalizeText(data_get($ubigeo, 'departamento')),
            $this->normalizeText(data_get($row, 'department')),
            $this->normalizeText(data_get($sourceRecord, 'departamento')),
        );
        $province = $this->firstFilled(
            $this->normalizeText(data_get($ubigeo, 'provincia')),
            $this->normalizeText(data_get($row, 'province')),
            $this->normalizeText(data_get($sourceRecord, 'provincia')),
        );
        $district = $this->firstFilled(
            $this->normalizeText(data_get($ubigeo, 'distrito')),
            $this->normalizeText(data_get($row, 'district')),
            $this->normalizeText(data_get($sourceRecord, 'distrito')),
            $this->normalizeText(data_get($row, 'zone')),
            $this->normalizeText(data_get($sourceRecord, 'zona')),
            $this->districtFromPlace($this->firstFilled($place, data_get($sourceRecord, 'nombre')))
        );

        $latitude = $this->nullableFloat($this->firstFilled(
            $row['latitude'] ?? null,
            $sourceRecord['latitud'] ?? null,
        ));

        $longitude = $this->nullableFloat($this->firstFilled(
            $row['longitude'] ?? null,
            $sourceRecord['longitud'] ?? null,
        ));

        $general = $this->firstText([
            $schedule['general'] ?? null,
            $sourceRecord['hora_atencion'] ?? null,
        ], uppercase: true);

        $sunday = $this->firstText([
            $schedule['sunday'] ?? null,
            $sourceRecord['hora_domingo'] ?? null,
        ], uppercase: true);

        $classificationCategory = $this->firstText([
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

        $textoChosenTerrestre = $this->buildChosenText($externalId, $department, $province, $district, $name, 'TERRESTRE');
        $textoChosenAereo = filled($row['services']['air'] ?? null) || filled($sourceRecord['ter_aereo'] ?? null)
            ? $this->buildChosenText($externalId, $department, $province, $district, $name, 'AEREO')
            : null;

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
            'schedule_general' => $general,
            'schedule_sunday' => $sunday,
            'classification_category' => $classificationCategory,
            'classification_sends_category' => $sendsCategory,
            'classification_receives_category' => $receivesCategory,
            'ubigeo_id' => $ubigeo?->id,
            'ubigeo_code' => $ubigeoCode,
            'is_operations_center' => mb_strtoupper(trim((string) $classificationCategory), 'UTF-8') === 'GRANDE / CO',
            'texto_chosen_terrestre' => $textoChosenTerrestre,
            'texto_chosen_aereo' => $textoChosenAereo,
            'map_url' => $mapUrl,
            'source' => $row['source'] ?? null,
            'source_record' => $sourceRecord ?: null,
        ];
    }

    private function normalizeUbigeoCode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === '') {
            return null;
        }

        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    private function districtFromPlace(?string $place): ?string
    {
        if (! filled($place)) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode('/', (string) $place)), static fn ($value) => $value !== ''));

        return $parts[2] ?? null;
    }

    private function firstFilled(mixed ...$values): mixed
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return $value;
        }

        return null;
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        return preg_replace('/\s+/u', ' ', $value);
    }

    private function firstText(array $values, bool $uppercase = false): ?string
    {
        foreach ($values as $value) {
            $value = $this->normalizeText($value);
            if ($value === null) {
                continue;
            }

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

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || strtolower($value) === 'null') {
                return null;
            }
        }

        return is_numeric($value) ? (float) $value : null;
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
}
