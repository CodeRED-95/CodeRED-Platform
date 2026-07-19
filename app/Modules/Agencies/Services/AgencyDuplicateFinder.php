<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;

class AgencyDuplicateFinder
{
    public function find(array $data): ?Agency
    {
        return $this->resolve($data)['agency'];
    }

    /** @return array{agency: ?Agency, conflict: ?string} */
    public function resolve(array $data): array
    {
        $matches = collect();

        if (filled($data['external_id'] ?? null)) {
            $matches->push(Agency::withTrashed()->where('external_id', $data['external_id'])->first());
        }

        if (filled($data['source_reference'] ?? null)) {
            $matches->push(Agency::withTrashed()->where('source', 'github_gist')->where('source_reference', $data['source_reference'])->first());
        }

        if (filled($data['code'] ?? null)) {
            $matches->push(Agency::withTrashed()->where('code', $data['code'])->first());
        }

        $matches = $matches->filter()->unique('id')->values();
        if ($matches->count() > 1) {
            return ['agency' => null, 'conflict' => 'El ID externo, Code y referencia de origen apuntan a agencias distintas.'];
        }

        if ($matches->isNotEmpty()) {
            return ['agency' => $matches->first(), 'conflict' => null];
        }

        if (blank($data['name'] ?? null)) {
            return ['agency' => null, 'conflict' => null];
        }

        $agency = Agency::query()
            ->whereRaw('lower(unaccent(name)) = lower(unaccent(?))', [$data['name']])
            ->whereRaw('lower(unaccent(department)) = lower(unaccent(?))', [$data['department']])
            ->whereRaw('lower(unaccent(province)) = lower(unaccent(?))', [$data['province']])
            ->whereRaw('lower(unaccent(district)) = lower(unaccent(?))', [$data['district']])
            ->first();

        return ['agency' => $agency, 'conflict' => null];
    }
}
