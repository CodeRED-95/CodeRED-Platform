<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;

class AgencyPlaceGenerator
{
    public function __invoke(Agency $agency): ?string
    {
        return self::fromSegments([
            $agency->department,
            $agency->province,
            $agency->district,
            $agency->name,
        ]);
    }

    public static function fromSegments(array $segments): ?string
    {
        $parts = collect($segments)
            ->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->filter(fn (mixed $value): bool => $value !== null && $value !== '');

        return $parts->isEmpty() ? null : $parts->implode(' / ');
    }
}
