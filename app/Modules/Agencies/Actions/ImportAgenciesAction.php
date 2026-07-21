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
        return DB::transaction(fn (): array => $this->executeWithinTransaction($import, $rows, $defaultStatus));
    }

    private function executeWithinTransaction(AgencyImport $import, array $rows, ?string $defaultStatus): array
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
            'restored' => 0,
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
                foreach (['external_id', 'name', 'short_name', 'department', 'province', 'district', 'address', 'reference', 'phone', 'secondary_phone', 'email', 'schedule', 'services', 'observations', 'source_text', 'texto_chosen_terrestre', 'texto_chosen_aereo', 'map_url', 'size', 'status', 'is_operations_center', 'has_moved', 'moved_to_address', 'move_notice', 'moved_at'] as $field) {
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

        $backupIdMap = [];
        foreach ($rows as $row) {
            if (! ($row['_backup_record'] ?? false)) {
                continue;
            }
            $data = AgencyImportNormalizer::transform($row)->normalized;
            $agency = $this->duplicateFinder->find($data);
            if ($agency === null) {
                continue;
            }
            if (is_numeric($row['_backup_id'] ?? null)) {
                $backupIdMap[(int) $row['_backup_id']] = $agency->id;
            }
            $agency->update(['moved_to_agency_id' => null]);
            if (($row['_backup_deleted_at'] ?? null) === null && $agency->trashed()) {
                $agency->restore();
                $summary['restored']++;
            } elseif (($row['_backup_deleted_at'] ?? null) !== null && ! $agency->trashed()) {
                $agency->delete();
            }
        }

        foreach ($rows as $row) {
            $oldTarget = $row['_backup_moved_to_id'] ?? null;
            $oldSource = $row['_backup_id'] ?? null;
            if ($oldTarget === null) {
                continue;
            }
            if (! is_numeric($oldSource) || ! is_numeric($oldTarget) || ! isset($backupIdMap[(int) $oldSource], $backupIdMap[(int) $oldTarget])) {
                $summary['warnings']++;

                continue;
            }
            Agency::withTrashed()->whereKey($backupIdMap[(int) $oldSource])->update(['moved_to_agency_id' => $backupIdMap[(int) $oldTarget]]);
        }

        return $summary;
    }
}
