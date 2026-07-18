<?php

namespace App\Modules\Agencies\Support;

final class AgencyTextNormalizer
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
}
