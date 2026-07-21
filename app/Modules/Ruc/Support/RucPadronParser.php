<?php

namespace App\Modules\Ruc\Support;

final class RucPadronParser
{
    public function parse(string $line, string $delimiter = '|', string $encoding = 'latin-1'): array
    {
        $utf8 = mb_convert_encoding(rtrim($line, "\r\n"), 'UTF-8', $encoding);
        $columns = array_map($this->clean(...), str_getcsv($utf8, $delimiter));
        if (in_array(mb_strtoupper($columns[0]), ['RUC', 'NUMERO_RUC', 'NÚMERO_RUC', 'NUMERO DE RUC'], true)) {
            return ['header' => true];
        }
        if (count($columns) < 2) {
            return ['error' => 'Número de columnas insuficiente.'];
        }
        if (! preg_match('/^\d{11}$/', $columns[0])) {
            return ['error' => 'RUC inválido.'];
        }
        if ($columns[1] === '') {
            return ['error' => 'Razón social vacía.'];
        }

        $columns = array_pad($columns, 15, '');
        $base = [
            'ruc' => $columns[0], 'razon_social' => $columns[1], 'estado' => $columns[2] ?: null,
            'condicion' => $columns[3] ?: null, 'ubigeo' => $columns[4] ?: null,
            'tipo_via' => null, 'nombre_via' => null, 'codigo_zona' => null, 'tipo_zona' => null,
            'numero' => null, 'interior' => null, 'lote' => null, 'departamento_direccion' => null,
            'manzana' => null, 'kilometro' => null, 'departamento' => null, 'provincia' => null,
            'distrito' => null, 'direccion' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
        if (array_filter(array_slice($columns, 11, 4)) !== []) {
            [$base['tipo_via'], $base['nombre_via'], $base['codigo_zona'], $base['tipo_zona'], $base['numero'], $base['interior'], $base['lote'], $base['departamento_direccion'], $base['manzana'], $base['kilometro']] = array_map(fn (string $value): ?string => $value ?: null, array_slice(array_pad($columns, 15, ''), 5, 10));
            $base['direccion'] = $this->address($base);
        } else {
            $base['direccion'] = $columns[7] ?: null;
            $base['provincia'] = $columns[8] ?: null;
            $base['departamento'] = $columns[9] ?: null;
            $base['distrito'] = $columns[10] ?: null;
            $base['direccion'] ??= implode(' - ', array_filter([$base['provincia'], $base['departamento'], $base['distrito']]));
        }

        return ['data' => $base];
    }

    private function clean(?string $value): string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B\"'");

        return in_array(mb_strtoupper($value), ['', '-', 'NULL'], true) ? '' : preg_replace('/\s+/u', ' ', $value);
    }

    private function address(array $data): ?string
    {
        $parts = array_filter([
            $data['tipo_via'], $data['nombre_via'], $data['numero'] ? 'NRO '.$data['numero'] : null,
            $data['interior'] ? 'INT '.$data['interior'] : null, $data['lote'] ? 'LT '.$data['lote'] : null,
            $data['manzana'] ? 'MZA '.$data['manzana'] : null, $data['kilometro'] ? 'KM '.$data['kilometro'] : null,
            $data['tipo_zona'], $data['codigo_zona'], $data['departamento_direccion'],
        ]);

        return $parts === [] ? null : implode(' ', $parts);
    }
}
