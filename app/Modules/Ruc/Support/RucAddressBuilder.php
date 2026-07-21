<?php

namespace App\Modules\Ruc\Support;

final class RucAddressBuilder
{
    public function build(array $parts): ?string
    {
        $parts = array_filter(array_map(fn (mixed $value): string => trim((string) $value), $parts), fn (string $value): bool => ! in_array(mb_strtoupper($value), ['', '-', '--', 'NULL', 'N/A'], true));
        $address = preg_replace('/\s+/u', ' ', implode(' ', $parts));
        $address = preg_replace('/\s+([,.])/u', '$1', (string) $address);
        $address = trim((string) $address, " \t\n\r\0\x0B,;-");

        return $address === '' ? null : $address;
    }
}
