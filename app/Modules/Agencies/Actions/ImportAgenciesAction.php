<?php

namespace App\Modules\Agencies\Actions;

use App\Modules\Agencies\Enums\AgencyImportStrategy;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImport;
use App\Modules\Agencies\Models\AgencyImportFailure;
use App\Modules\Agencies\Services\AgencyDuplicateFinder;
use App\Modules\Agencies\Support\AgencyImportNormalizer;
use App\Modules\Agencies\Support\AgencyVersion;
use Illuminate\Support\Facades\DB;

class ImportAgenciesAction
{
    public function __construct(private readonly AgencyDuplicateFinder $duplicateFinder) {}

    public function execute(AgencyImport $import, array $rows, ?string $defaultStatus = null): array
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

            DB::transaction(function () use ($transformed, $import, &$summary, $defaultStatus): void {
                $data = $transformed->normalized;
                $existing = $this->duplicateFinder->find($data);

                if (! $existing) {
                    $version = AgencyVersion::bump();
                    Agency::query()->create([
                        ...$data,
                        'status' => AgencyStatus::from($defaultStatus ?: $data['status']),
                        'data_version' => $version,
                    ]);
                    $summary['imported']++;

                    return;
                }

                $strategy = AgencyImportStrategy::from($import->strategy);
                if (in_array($strategy, [AgencyImportStrategy::IgnoreExisting, AgencyImportStrategy::CreateOnlyNew], true)) {
                    $summary['skipped']++;

                    return;
                }

                if ($strategy === AgencyImportStrategy::MarkConflicts) {
                    AgencyImportFailure::query()->create([
                        'agency_import_id' => $import->id,
                        'row_number' => 0,
                        'raw_data' => $transformed->raw,
                        'errors' => ['Conflicto detectado con un registro existente.'],
                        'created_at' => now(),
                    ]);
                    $summary['failed']++;

                    return;
                }

                $changes = [];
                foreach (['name', 'department', 'province', 'district', 'address', 'source_text', 'map_url', 'size'] as $field) {
                    $value = $data[$field] ?? null;
                    if ($value === null || $value === '') {
                        continue;
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

        return $summary;
    }
}
