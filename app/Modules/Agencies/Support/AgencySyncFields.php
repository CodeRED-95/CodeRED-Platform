<?php

declare(strict_types=1);

namespace App\Modules\Agencies\Support;

/**
 * Campos que la sincronizacion Shalom compara y escribe.
 *
 * Fuente unica a proposito. Antes la comparacion recorria todo el array
 * normalizado mientras que la escritura solo aplicaba un subconjunto, asi que
 * los campos de fuera —`source_record`, que ni siquiera es columna, o
 * `map_url`— aparecian como diferencia en cada ejecucion y no habia forma de
 * hacerlos converger: la vista previa proponia actualizar las mismas agencias
 * una y otra vez por mucho que se confirmara la importacion.
 */
final class AgencySyncFields
{
    /**
     * Escala decimal real de las columnas `latitude` y `longitude`
     * (`numeric(15,12)`). Lo que llega con mas decimales se redondea al
     * guardar, de modo que compararlo sin redondear tambien producia una
     * diferencia perpetua.
     */
    public const COORDINATE_SCALE = 12;

    /** @var list<string> */
    public const FIELDS = [
        'external_id',
        'code',
        'name',
        'department',
        'province',
        'district',
        'address',
        'latitude',
        'longitude',
        'schedule_general',
        'schedule_sunday',
        'classification_category',
        'classification_sends_category',
        'classification_receives_category',
        'texto_chosen_terrestre',
        'texto_chosen_aereo',
    ];

    /** @var list<string> */
    public const COORDINATES = ['latitude', 'longitude'];

    public static function isCoordinate(string $field): bool
    {
        return in_array($field, self::COORDINATES, true);
    }

    public static function roundCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, self::COORDINATE_SCALE);
    }

    /**
     * Compara el valor guardado con el entrante respetando el tipo real de
     * cada campo.
     *
     * Las coordenadas se leen del modelo como cadena (ver NullableCoordinate)
     * y llegan como float, de modo que `!==` siempre daba distinto. El resto
     * se compara como texto para que un entero y su cadena equivalente
     * —`external_id`— no cuenten como cambio.
     */
    public static function matches(string $field, mixed $current, mixed $incoming): bool
    {
        if (self::isCoordinate($field)) {
            return self::roundCoordinate($current) === self::roundCoordinate($incoming);
        }

        if ($current === null || $incoming === null) {
            return $current === $incoming;
        }

        if (is_bool($current) || is_bool($incoming)) {
            return (bool) $current === (bool) $incoming;
        }

        return (string) $current === (string) $incoming;
    }
}
