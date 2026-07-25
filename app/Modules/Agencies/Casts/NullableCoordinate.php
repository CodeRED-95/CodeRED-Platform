<?php

namespace App\Modules\Agencies\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast seguro para coordenadas almacenadas en columnas NUMERIC/DECIMAL.
 *
 * Convierte cadenas vacías y valores heredados no numéricos a null al leer,
 * y rechaza valores no numéricos al escribir para evitar datos corruptos.
 */
class NullableCoordinate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return $this->normalize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("El campo {$key} debe ser una coordenada numérica válida.");
        }

        $number = (float) $value;
        $minimum = $key === 'latitude' ? -90 : -180;
        $maximum = $key === 'latitude' ? 90 : 180;

        if ($number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException("El campo {$key} debe estar entre {$minimum} y {$maximum}.");
        }

        return $this->normalize((string) $value);
    }

    private function normalize(string $value): string
    {
        $normalized = number_format((float) $value, 12, '.', '');
        $normalized = rtrim(rtrim($normalized, '0'), '.');

        return $normalized === '-0' ? '0' : $normalized;
    }
}
