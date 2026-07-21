<?php

namespace App\Modules\Ruc\Support;

use InvalidArgumentException;

final class EncodingNormalizer
{
    public static function normalize(?string $encoding): string
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

        throw new InvalidArgumentException("La codificación configurada para el padrón RUC no es compatible: [{$original}].");
    }
}
