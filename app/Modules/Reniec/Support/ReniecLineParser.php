<?php

namespace App\Modules\Reniec\Support;

use App\Modules\Ruc\Support\EncodingNormalizer;

final class ReniecLineParser
{
    public function parse(string $line, int $number, string $delimiter = '|', string $encoding = 'ISO-8859-1'): array
    {
        $line = rtrim($line, "\r\n");
        if ($line === '') {
            return ['error' => 'empty_line', 'line' => $number];
        }
        if (! mb_check_encoding($line, 'UTF-8')) {
            $line = mb_convert_encoding($line, 'UTF-8', EncodingNormalizer::normalize($encoding));
        }
        $columns = array_map(fn ($v) => trim((string) $v), str_getcsv($line, $delimiter));
        $columns[0] = preg_replace('/^\x{FEFF}/u', '', $columns[0] ?? '') ?? '';
        if (mb_strtoupper($columns[0]) === 'DNI') {
            return ['header' => true];
        }
        if (count($columns) < 4) {
            return ['error' => 'invalid_column_count', 'line' => $number];
        }
        if (! preg_match('/^\d{8}$/', $columns[0])) {
            return ['error' => 'invalid_dni', 'line' => $number];
        }
        if ($columns[1] === '' || $columns[2] === '' || $columns[3] === '') {
            return ['error' => 'missing_required_field', 'line' => $number];
        }
        $date = null;
        if (($columns[4] ?? '') !== '') {
            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $columns[4]);
            if (! $parsed) {
                return ['error' => 'invalid_date', 'line' => $number];
            } $date = $parsed->format('Y-m-d');
        }

        return ['data' => ['dni' => $columns[0], 'nombres' => $columns[1], 'apellido_paterno' => $columns[2], 'apellido_materno' => $columns[3], 'fecha_nacimiento' => $date, 'genero' => ($columns[5] ?? '') ?: null, 'ubigeo' => preg_match('/^\d{6}$/', $columns[6] ?? '') ? $columns[6] : null]];
    }
}
