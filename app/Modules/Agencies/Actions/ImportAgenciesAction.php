<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use App\Modules\Agencies\Models\AgencyImportFailure;
use App\Modules\Agencies\Support\AgencyImportNormalizer;
use App\Modules\Agencies\Support\AgencyVersion;
use Illuminate\Support\Facades\DB;

class ImportAgenciesAction
{
    public function execute(AgencyImport $import, array $rows): array
    {
        $summary = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        foreach ($rows as $index => $row) {
            $transformed = AgencyImportNormalizer::transform($row);

            if (! $transformed->valid) {
                $summary['failed']++;
                AgencyImportFailure::query()->create([
                    'agency_import_id' => $import->id,
                    'row_number' => $index + 1,
                    'raw_data' => $row,
                    'errors' => $transformed->errors,
                    'created_at' => now(),
                ]);
                continue;
            }

            DB::transaction(function () use ($transformed, $import, &$summary): void {
                $data = $transformed->normalized;
                $existing = Agency::query()
                    ->where('source', 'github_gist')
                    ->where('source_reference', $data['source_reference'])
                    ->first();

                if (! $existing) {
                    $existing = Agency::query()->where('code', $data['code'])->first();
                }

                if (! $existing) {
                    $existing = Agency::query()
                        ->whereRaw('lower(unaccent(name)) = lower(unaccent(?))', [$data['name']])
                        ->whereRaw('lower(unaccent(department)) = lower(unaccent(?))', [$data['department']])
                        ->whereRaw('lower(unaccent(province)) = lower(unaccent(?))', [$data['province']])
                        ->whereRaw('lower(unaccent(district)) = lower(unaccent(?))', [$data['district']])
                        ->first();
                }

                if (! $existing) {
                    $version = AgencyVersion::bump();
                    Agency::query()->create([
                        ...$data,
                        'status' => AgencyStatus::from($data['status']),
                        'data_version' => $version,
                    ]);
                    $summary['imported']++;
                    return;
                }

                $changes = [];
                foreach (['name', 'department', 'province', 'district', 'address', 'source_text', 'map_url', 'size', 'status'] as $field) {
                    $value = $data[$field] ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if ($field === 'status') {
                        $value = AgencyStatus::from($value);
                    }
                    $changes[$field] = $value;
                }

                if (($data['latitude'] ?? null) !== null && ($data['longitude'] ?? null) !== null) {
                    $changes['latitude'] = $data['latitude'];
                    $changes['longitude'] = $data['longitude'];
                }

                if ($changes !== []) {
                    $changes['data_version'] = AgencyVersion::bump();
                    $existing->fill($changes)->save();
                    $summary['updated']++;
                } else {
                    $summary['skipped']++;
                }
            });
        }

        AgencyVersion::bump();

        return $summary;
    }
}
