<?php

namespace RucTool\Services;

/**
 * Parser del padrón reducido de RUC (SUNAT), replicando exactamente la lógica
 * de App\Modules\Ruc\Support\RucPadronParser en CodeRED-Platform para que los
 * registros importados aquí sean idénticos a los que produciría producción.
 *
 * Formato: 15 columnas separadas por '|', encoding típico ISO-8859-1.
 * Columnas: ruc, razon_social, estado, condicion, ubigeo, tipo_via, nombre_via,
 * codigo_zona, tipo_zona, numero, interior, lote, departamento_direccion, manzana, kilometro
 */
class PadronParser
{
    public function parse(string $line, string $delimiter = '|', string $encoding = 'ISO-8859-1'): array
    {
        $utf8 = $this->toUtf8(rtrim($line, "\r\n"), $encoding);
        $columns = array_map($this->clean(...), str_getcsv($utf8, $delimiter));
        $columns[0] = preg_replace('/^\x{FEFF}/u', '', $columns[0] ?? '') ?? '';

        $header = mb_strtoupper(implode('|', $columns));
        if (
            mb_strtoupper($columns[0]) === 'RUC'
            || str_contains($header, 'NOMBRE O RAZÓN SOCIAL')
            || str_contains($header, 'NOMBRE O RAZON SOCIAL')
        ) {
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

        $data = [
            'ruc' => $columns[0],
            'razon_social' => $columns[1],
            'estado' => $columns[2] ?: null,
            'condicion' => $columns[3] ?: null,
            'ubigeo' => $ubigeo,
            'tipo_via' => $columns[5] ?: null,
            'nombre_via' => $columns[6] ?: null,
            'codigo_zona' => $columns[7] ?: null,
            'tipo_zona' => $columns[8] ?: null,
            'numero' => $columns[9] ?: null,
            'interior' => $columns[10] ?: null,
            'lote' => $columns[11] ?: null,
            'departamento_direccion' => $columns[12] ?: null,
            'manzana' => $columns[13] ?: null,
            'kilometro' => $columns[14] ?: null,
            'departamento' => null,
            'provincia' => null,
            'distrito' => null,
            'direccion' => $this->buildAddress(array_slice($columns, 5)),
        ];

        return ['data' => $data];
    }

    /**
     * Construye la dirección concatenada a partir de las partes (tipo_via..kilometro),
     * idéntico a App\Modules\Ruc\Support\RucAddressBuilder::build().
     */
    public function buildAddress(array $parts): ?string
    {
        $parts = array_filter(
            array_map(fn (mixed $value): string => trim((string) $value), $parts),
            fn (string $value): bool => ! in_array(mb_strtoupper($value), ['', '-', '--', 'NULL', 'N/A'], true)
        );

        $address = preg_replace('/\s+/u', ' ', implode(' ', $parts));
        $address = preg_replace('/\s+([,.])/u', '$1', (string) $address);
        $address = trim((string) $address, " \t\n\r\0\x0B,;-");

        return $address === '' ? null : $address;
    }

    private function toUtf8(string $value, string $encoding): string
    {
        $source = $this->normalizeEncoding($encoding);
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', $source);
    }

    /**
     * Idéntico a App\Modules\Ruc\Support\EncodingNormalizer::normalize().
     */
    private function normalizeEncoding(?string $encoding): string
    {
        $original = trim((string) $encoding);
        $value = strtolower($original);

        $known = match ($value) {
            'latin-1', 'latin1', 'latin_1', 'iso-8859-1', 'iso8859-1' => 'ISO-8859-1',
            'cp1252', 'windows1252', 'windows-1252', 'win-1252' => 'Windows-1252',
            'utf8', 'utf-8' => 'UTF-8',
            default => null,
        };

        if ($known !== null) {
            return $known;
        }

        foreach (mb_list_encodings() as $supported) {
            $aliases = mb_encoding_aliases($supported);
            if (strcasecmp($original, $supported) === 0 || in_array($value, array_map('strtolower', $aliases), true)) {
                return $supported;
            }
        }

        throw new \InvalidArgumentException("La codificación configurada para el padrón RUC no es compatible: [{$original}].");
    }

    private function clean(?string $value): string
    {
        $value = trim((string) $value, " \t\n\r\0\x0B\"'");

        return in_array(mb_strtoupper($value), ['', '-', 'NULL'], true) ? '' : preg_replace('/\s+/u', ' ', $value);
    }
}
