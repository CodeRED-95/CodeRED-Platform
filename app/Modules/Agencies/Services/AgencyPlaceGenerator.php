<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;

class AgencyPlaceGenerator
{
    public function __invoke(Agency $agency): ?string
    {
        $parts = [
            $agency->department,
            $agency->province,
            $agency->district,
            $agency->name,
        ];

        $nonEmptyParts = array_filter($parts, fn ($part) => !empty(trim((string)$part)));

        if (empty($nonEmptyParts)) {
            return null;
        }

        return implode(' / ', $nonEmptyParts);
    }
}
