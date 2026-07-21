<?php

namespace App\Modules\Ruc\Support;

final class RucPadronParser
{
    public function __construct(private readonly RucAddressBuilder $addressBuilder) {}

    public function parse(string $line, string $delimiter = '|', string $encoding = 'ISO-8859-1'): array
    {
        $utf8 = $this->toUtf8(rtrim($line, "\r\n"), $encoding);
        $columns = array_map($this->clean(...), str_getcsv($utf8, $delimiter));
        $columns[0] = preg_replace('/^\x{FEFF}/u', '', $columns[0] ?? '') ?? '';
        $header = mb_strtoupper(implode('|', $columns));
        if (mb_strtoupper($columns[0]) === 'RUC' || str_contains($header, 'NOMBRE O RAZÓN SOCIAL') || str_contains($header, 'NOMBRE O RAZON SOCIAL')) {
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
        $ubigeo = preg_match('/^\d{6}$/', $columns[4]) ? $columns[4] : null;
        $base = [
            'ruc' => $columns[0], 'razon_social' => $columns[1], 'estado' => $columns[2] ?: null,
            'condicion' => $columns[3] ?: null, 'ubigeo' => $ubigeo,
            'tipo_via' => $columns[5] ?: null, 'nombre_via' => $columns[6] ?: null,
            'codigo_zona' => $columns[7] ?: null, 'tipo_zona' => $columns[8] ?: null,
            'numero' => $columns[9] ?: null, 'interior' => $columns[10] ?: null,
            'lote' => $columns[11] ?: null, 'departamento_direccion' => $columns[12] ?: null,
            'manzana' => $columns[13] ?: null, 'kilometro' => $columns[14] ?: null,
            'departamento' => null, 'provincia' => null, 'distrito' => null,
            'direccion' => $this->addressBuilder->build(array_slice($columns, 5)),
            'created_at' => now(), 'updated_at' => now(),
        ];

        return ['data' => $base];
    }

    public function preview(string $line, string $encoding): string
    {
        return mb_substr($this->toUtf8(trim($line), $encoding), 0, 300);
    }

    private function toUtf8(string $value, string $encoding): string
    {
        $source = EncodingNormalizer::normalize($encoding);
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', $source);
    }

    private function clean(?string $value): string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B\"'");

        return in_array(mb_strtoupper($value), ['', '-', 'NULL'], true) ? '' : preg_replace('/\s+/u', ' ', $value);
    }
}
