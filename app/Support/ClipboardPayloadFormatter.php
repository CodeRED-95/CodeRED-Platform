<?php

declare(strict_types=1);

namespace App\Support;

class ClipboardPayloadFormatter
{
    public static function json(mixed $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function readable(array $payload): string
    {
        return trim(self::formatValue($payload));
    }

    private static function formatValue(mixed $value, int $level = 0, ?string $label = null): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $indent = str_repeat('  ', $level);
        $prefix = $label !== null ? $indent.$label.': ' : $indent;

        if (is_bool($value)) {
            return $prefix.($value ? 'Sí' : 'No');
        }

        if (is_scalar($value)) {
            return $prefix.(string) $value;
        }

        if (! is_array($value)) {
            return $prefix.json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === []) {
            return '';
        }

        $lines = [];
        $isList = array_is_list($value);

        foreach ($value as $key => $item) {
            $itemLabel = $isList ? '- ' : self::humanizeKey((string) $key).': ';
            if (is_array($item)) {
                $nestedLabel = $isList ? null : self::humanizeKey((string) $key);
                $formatted = self::formatValue($item, $level + 1, $nestedLabel);
                if ($formatted !== '') {
                    if ($nestedLabel !== null) {
                        $lines[] = $indent.$nestedLabel.':'.PHP_EOL.$formatted;
                    } else {
                        $lines[] = $indent.'-'.PHP_EOL.$formatted;
                    }
                }

                continue;
            }

            if ($item === null || $item === '') {
                continue;
            }

            $lines[] = $indent.$itemLabel.(is_bool($item) ? ($item ? 'Sí' : 'No') : (string) $item);
        }

        return implode(PHP_EOL, array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    private static function humanizeKey(string $key): string
    {
        $labels = [
            'dni' => 'DNI',
            'ruc' => 'RUC',
            'razon_social' => 'Razón Social',
            'apellido_paterno' => 'Apellido Paterno',
            'apellido_materno' => 'Apellido Materno',
            'nombre_completo' => 'Nombre Completo',
            'fecha_nacimiento' => 'Fecha de Nacimiento',
            'codigo_verificacion' => 'Código de Verificación',
            'condicion' => 'Condición',
            'ubigeo' => 'Ubigeo',
            'direccion' => 'Dirección',
            'departamento' => 'Departamento',
            'provincia' => 'Provincia',
            'distrito' => 'Distrito',
            'nombres' => 'Nombres',
            'estado' => 'Estado',
            'genero' => 'Género',
            'edad' => 'Edad',
        ];

        $normalized = strtolower($key);
        if (isset($labels[$normalized])) {
            return $labels[$normalized];
        }

        $acronyms = ['dni', 'ruc', 'api', 'url', 'id'];
        if (in_array(strtolower($key), $acronyms, true)) {
            return strtoupper($key);
        }

        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/(?<!^)[A-Z]/', ' $0', $key) ?? $key;

        return trim(mb_convert_case($key, MB_CASE_TITLE, 'UTF-8'));
    }
}
