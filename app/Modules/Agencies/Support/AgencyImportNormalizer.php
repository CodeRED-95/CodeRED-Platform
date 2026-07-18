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
            'grande' => 'large',
            'mediano', 'mediana' => 'medium',
            'pequeño', 'pequeno', 'pequeña', 'pequena' => 'small',
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

    public static function transform(array $row): AgencyImportRowData
    {
        $warnings = [];
        $errors = [];

        if (! array_key_exists('id', $row) || $row['id'] === null || $row['id'] === '') {
            $errors[] = 'El registro no contiene id.';
        }

        if (! array_key_exists('agencia', $row) || self::normalizeText($row['agencia'] ?? null) === null) {
            $errors[] = 'El registro no contiene agencia.';
        }

        $code = isset($row['id']) && $row['id'] !== ''
            ? self::generateCode($row['id'])
            : null;

        $name = self::normalizeText($row['agencia'] ?? null);
        $department = self::normalizeText($row['departamento'] ?? null);
        $province = self::normalizeText($row['provincia'] ?? null);
        $district = self::normalizeText($row['distrito'] ?? null);
        $address = self::normalizeText($row['direccion'] ?? null);
        $sourceText = self::normalizeText($row['texto_chosen'] ?? null);
        $mapUrl = self::normalizeText($row['link_mapa'] ?? null);
        $size = self::normalizeSize($row['tamano'] ?? null);
        $isOperationsCenter = self::parseOperationsCenter($row['co'] ?? false, $warnings);

        if (($row['tamano'] ?? null) !== null && $size === null) {
            $warnings[] = 'El tamaño no pudo normalizarse.';
        }

        [$latitude, $longitude] = self::parseCoordinates($mapUrl);
        if ($mapUrl !== null && $latitude === null && $longitude === null) {
            $warnings[] = 'El enlace de mapa no contiene coordenadas válidas.';
        }

        $normalized = [
            'code' => $code,
            'name' => $name,
            'department' => $department,
            'province' => $province,
            'district' => $district,
            'address' => $address,
            'source_text' => $sourceText,
            'map_url' => $mapUrl,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'size' => $size,
            'is_operations_center' => $isOperationsCenter,
            'source' => 'github_gist',
            'source_reference' => isset($row['id']) ? (string) $row['id'] : null,
            'status' => AgencyStatus::UnderReview->value,
            'slug' => $name ? self::slugifyUnique($name, (string) ($row['id'] ?? '')) : null,
            'has_moved' => false,
            'moved_to_agency_id' => null,
            'moved_to_address' => null,
            'move_notice' => null,
            'moved_at' => null,
        ];

        return AgencyImportRowData::make($row, $normalized, $warnings, $errors);
    }
}
