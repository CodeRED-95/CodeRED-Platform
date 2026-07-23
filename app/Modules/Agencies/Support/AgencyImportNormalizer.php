<?php

namespace App\Modules\Agencies\Support;

use App\Modules\Agencies\Data\AgencyImportRowData;
use App\Modules\Agencies\Enums\AgencyStatus;
use Illuminate\Support\Str;

final class AgencyImportNormalizer
{
    public static function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value);

        return $value === '' ? null : $value;
    }

    public static function parseOperationsCenter(mixed $value, array &$warnings = []): bool
    {
        $accepted = [true, false, 1, 0, 'true', 'false', '1', '0'];
        if (! in_array($value, $accepted, true)) {
            $warnings[] = 'El valor de co no pudo interpretarse como booleano.';

            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    public static function parseCoordinates(?string $mapUrl): array
    {
        if (! $mapUrl) {
            return [null, null];
        }

        if (preg_match('/destination=([-+]?\d+(?:\.\d+)?),([-+]?\d+(?:\.\d+)?)/', $mapUrl, $matches)) {
            $lat = (float) $matches[1];
            $lng = (float) $matches[2];

            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return [$lat, $lng];
            }
        }

        return [null, null];
    }

    public static function normalizeSize(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return match (mb_strtolower((string) $value)) {
            'grande', 'large' => 'large',
            'mediano', 'mediana', 'medium' => 'medium',
            'pequeño', 'pequeno', 'pequeña', 'pequena', 'small' => 'small',
            default => null,
        };
    }

    public static function slugifyUnique(string $name, string $suffix = ''): string
    {
        $slug = Str::slug($name);

        return $suffix ? $slug.'-'.$suffix : $slug;
    }

    public static function generateCode(int|string $id): string
    {
        return 'SHA-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    public static function classifyLegacyChosen(?string $value): ?string
    {
        $normalized = Str::ascii(mb_strtoupper((string) self::normalizeText($value)));
        $terrestre = str_contains($normalized, 'TERRESTRE');
        $aereo = str_contains($normalized, 'AEREO');

        return match (true) {
            $terrestre && ! $aereo => 'terrestre',
            $aereo && ! $terrestre => 'aereo',
            default => null,
        };
    }

    public static function transform(array $row): AgencyImportRowData
    {
        $warnings = [];
        $errors = [];

        $identity = $row['external_id'] ?? $row['id'] ?? null;
        if (($identity === null || $identity === '') && self::normalizeText($row['code'] ?? null) === null) {
            $errors[] = 'El registro no contiene ID externo ni code.';
        }

        $externalId = $identity === null || $identity === '' ? null : filter_var($identity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($identity !== null && $identity !== '' && $externalId === false) {
            $errors[] = 'El id debe ser un entero mayor que cero.';
        }

        if (! array_key_exists('agencia', $row) || self::normalizeText($row['agencia'] ?? null) === null) {
            $errors[] = 'El registro no contiene agencia.';
        }

        $providedCode = is_string($row['code'] ?? null) ? self::normalizeText($row['code']) : null;
        if (array_key_exists('code', $row) && $row['code'] !== null && ! is_string($row['code'])) {
            $errors[] = 'El code debe ser una cadena de texto.';
        }
        if ($providedCode !== null && mb_strlen($providedCode) > 50) {
            $errors[] = 'El code no puede superar 50 caracteres.';
        }

        $code = $providedCode !== null
            ? strtoupper($providedCode)
            : ($externalId !== false && $externalId !== null ? self::generateCode($externalId) : null);

        $name = self::normalizeText($row['agencia'] ?? null);
        $old_name = self::normalizeText($row['nombre_anterior'] ?? $row['old_name'] ?? null);
        $department = self::normalizeText($row['departamento'] ?? null);
        $province = self::normalizeText($row['provincia'] ?? null);
        $district = self::normalizeText($row['distrito'] ?? null);
        $address = self::normalizeText($row['direccion'] ?? null);
        $sourceText = self::normalizeText($row['texto_chosen'] ?? null);
        $chosenTerrestre = self::normalizeText($row['texto_chosen_terrestre'] ?? null);
        $chosenAereo = self::normalizeText($row['texto_chosen_aereo'] ?? null);
        $legacyClassification = self::classifyLegacyChosen($sourceText);

        if ($sourceText !== null && $legacyClassification === 'terrestre' && $chosenTerrestre === null) {
            $chosenTerrestre = $sourceText;
            $warnings[] = 'texto_chosen heredado clasificado como terrestre.';
        } elseif ($sourceText !== null && $legacyClassification === 'aereo' && $chosenAereo === null) {
            $chosenAereo = $sourceText;
            $warnings[] = 'texto_chosen heredado clasificado como aéreo.';
        } elseif ($sourceText !== null && $legacyClassification === null) {
            $warnings[] = 'texto_chosen heredado no pudo clasificarse; se conservó en source_text.';
        }
        $mapUrl = self::normalizeText($row['link_mapa'] ?? null);
        $size = self::normalizeSize($row['tamano'] ?? null);
        $isOperationsCenter = self::parseOperationsCenter($row['co'] ?? false, $warnings);
        $status = AgencyStatus::tryFrom((string) ($row['status'] ?? '')) ?? AgencyStatus::UnderReview;

        if (($row['tamano'] ?? null) !== null && $size === null) {
            $warnings[] = 'El tamaño no pudo normalizarse.';
        }

        [$latitude, $longitude] = self::parseCoordinates($mapUrl);
        $latitude = self::coordinate($row['latitude'] ?? $latitude, -90, 90);
        $longitude = self::coordinate($row['longitude'] ?? $longitude, -180, 180);
        if ($mapUrl !== null && $latitude === null && $longitude === null) {
            $warnings[] = 'El enlace de mapa no contiene coordenadas válidas.';
        }

        $normalized = [
            'external_id' => $externalId === false ? null : $externalId,
            'code' => $code,
            'name' => $name,
            'old_name' => $old_name,
            'department' => $department,
            'province' => $province,
            'district' => $district,
            'address' => $address,
            'source_text' => $sourceText,
            'texto_chosen_terrestre' => $chosenTerrestre,
            'texto_chosen_aereo' => $chosenAereo,
            'map_url' => $mapUrl,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'size' => $size,
            'is_operations_center' => $isOperationsCenter,
            'short_name' => self::normalizeText($row['short_name'] ?? null),
            'reference' => self::normalizeText($row['reference'] ?? null),
            'phone' => self::normalizeText($row['phone'] ?? null),
            'secondary_phone' => self::normalizeText($row['secondary_phone'] ?? null),
            'email' => self::normalizeText($row['email'] ?? null),
            'schedule' => self::normalizeText($row['schedule'] ?? null),
            'services' => self::jsonArray($row['services'] ?? []),
            'observations' => self::normalizeText($row['observations'] ?? null),
            'source' => self::normalizeText($row['source'] ?? null) ?? 'github_gist',
            'source_reference' => self::normalizeText($row['source_reference'] ?? null) ?? ($externalId === false || $externalId === null ? null : (string) $externalId),
            'status' => $status->value,
            'slug' => $name ? self::slugifyUnique($name, (string) ($row['id'] ?? '')) : null,
            'has_moved' => self::boolean($row['has_moved'] ?? false),
            'moved_to_agency_id' => null,
            'moved_to_address' => self::normalizeText($row['moved_to_address'] ?? null),
            'move_notice' => self::normalizeText($row['move_notice'] ?? null),
            'moved_at' => self::normalizeText($row['moved_at'] ?? null),
        ];

        return AgencyImportRowData::make($row, $normalized, $warnings, $errors);
    }

    private static function coordinate(mixed $value, float $minimum, float $maximum): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $coordinate = (float) $value;

        return $coordinate >= $minimum && $coordinate <= $maximum ? $coordinate : null;
    }

    private static function jsonArray(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) && array_is_list($value) ? $value : [];
    }

    private static function boolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true'], true);
    }
}
