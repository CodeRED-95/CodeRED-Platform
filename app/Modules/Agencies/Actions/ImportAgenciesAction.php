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
            'warnings' => 0,
            'legacy_classified' => 0,
            'legacy_unclassified' => 0,
            'identity_conflicts' => 0,
        ];

        $seenExternalIds = [];

        foreach ($rows as $index => $row) {
            $transformed = AgencyImportNormalizer::transform($row);

            $externalId = $transformed->normalized['external_id'] ?? null;
            if (is_int($externalId) && isset($seenExternalIds[$externalId])) {
                $summary['failed']++;
                AgencyImportFailure::query()->create([
                    'agency_import_id' => $import->id,
                    'row_number' => $index + 1,
                    'raw_data' => $row,
                    'errors' => ['El ID externo está duplicado dentro del archivo.'],
                    'created_at' => now(),
                ]);

                continue;
            }
            if (is_int($externalId)) {
                $seenExternalIds[$externalId] = true;
            }

            $summary['warnings'] += count($transformed->warnings);
            foreach ($transformed->warnings as $warning) {
                $summary[str_contains($warning, 'no pudo clasificarse') ? 'legacy_unclassified' : (str_contains($warning, 'heredado clasificado') ? 'legacy_classified' : 'warnings')] += str_contains($warning, 'texto_chosen heredado') ? 1 : 0;
            }

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

            DB::transaction(function () use ($transformed, $import, &$summary, $defaultStatus, $index): void {
                $data = $transformed->normalized;
                $resolution = $this->duplicateFinder->resolve($data);
                if ($resolution['conflict'] !== null) {
                    AgencyImportFailure::query()->create([
                        'agency_import_id' => $import->id,
                        'row_number' => $index + 1,
                        'raw_data' => $transformed->raw,
                        'errors' => [$resolution['conflict']],
                        'created_at' => now(),
                    ]);
                    $summary['failed']++;
                    $summary['identity_conflicts']++;

                    return;
                }
                $existing = $resolution['agency'];

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
                foreach (['external_id', 'name', 'department', 'province', 'district', 'address', 'source_text', 'texto_chosen_terrestre', 'texto_chosen_aereo', 'map_url', 'size'] as $field) {
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
