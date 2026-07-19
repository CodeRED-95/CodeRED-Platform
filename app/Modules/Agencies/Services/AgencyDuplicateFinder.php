<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;

class AgencyDuplicateFinder
{
    public function find(array $data): ?Agency
    {
        $existing = Agency::query()
            ->where('source', 'github_gist')
            ->where('source_reference', $data['source_reference'] ?? null)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $existing = Agency::query()->where('code', $data['code'] ?? null)->first();
        if ($existing !== null) {
            return $existing;
        }

        if (blank($data['name'] ?? null)) {
            return null;
        }

        return Agency::query()
            ->whereRaw('lower(unaccent(name)) = lower(unaccent(?))', [$data['name']])
            ->whereRaw('lower(unaccent(department)) = lower(unaccent(?))', [$data['department']])
            ->whereRaw('lower(unaccent(province)) = lower(unaccent(?))', [$data['province']])
            ->whereRaw('lower(unaccent(district)) = lower(unaccent(?))', [$data['district']])
            ->first();
    }
}
