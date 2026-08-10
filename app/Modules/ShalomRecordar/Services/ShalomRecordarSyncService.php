<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Services;

use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ShalomRecordarSyncService
{
    public function upsertInstallation(User $user, array $data, ?Request $request = null): ShalomRecordarInstallation
    {
        return DB::transaction(function () use ($user, $data, $request): ShalomRecordarInstallation {
            /** @var ShalomRecordarInstallation $installation */
            $installation = ShalomRecordarInstallation::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'installation_uuid' => (string) $data['installation_uuid'],
                ],
                [
                    'extension_version' => (string) $data['extension_version'],
                    'device_name' => Arr::get($data, 'device_name', Arr::get($data, 'installation.device_name')),
                    'browser_name' => Arr::get($data, 'browser_name', Arr::get($data, 'installation.browser_name')),
                    'browser_version' => Arr::get($data, 'browser_version', Arr::get($data, 'installation.browser_version')),
                    'platform_name' => Arr::get($data, 'platform_name', Arr::get($data, 'installation.platform_name')),
                    'platform_version' => Arr::get($data, 'platform_version', Arr::get($data, 'installation.platform_version')),
                    'last_synced_at' => now(),
                    'last_seen_at' => now(),
                ]
            );

            if ($request !== null) {
                $installation->forceFill(['last_seen_at' => now()])->save();
            }

            return $installation;
        });
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array{created:int, updated:int, cursor:?string}
     */
    public function syncRecords(User $user, ShalomRecordarInstallation $installation, array $records): array
    {
        $created = 0;
        $updated = 0;
        $cursor = null;

        DB::transaction(function () use ($user, $installation, $records, &$created, &$updated, &$cursor): void {
            foreach ($records as $record) {
                $field = trim((string) ($record['field'] ?? ''));
                $value = trim((string) ($record['value'] ?? ''));
                $recordedAt = (string) ($record['timestamp'] ?? now()->toISOString());
                $externalRecordId = trim((string) ($record['record_id'] ?? $record['id'] ?? ''));
                $cursor = (string) ($record['cursor'] ?? $cursor ?? $recordedAt);
                $recordHash = hash('sha256', json_encode([
                    'installation_uuid' => $installation->installation_uuid,
                    'field' => $field,
                    'value' => $value,
                    'timestamp' => $recordedAt,
                    'record_id' => $externalRecordId,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                $recordModel = ShalomRecordarRecord::query()->updateOrCreate(
                    [
                        'installation_id' => $installation->id,
                        'record_hash' => $recordHash,
                    ],
                    [
                        'user_id' => $user->id,
                        'installation_uuid' => $installation->installation_uuid,
                        'external_record_id' => $externalRecordId !== '' ? $externalRecordId : null,
                        'field' => $field,
                        'value' => $value,
                        'recorded_at' => $recordedAt,
                        'sync_cursor' => $cursor,
                        'payload' => $record,
                    ]
                );

                $recordModel->wasRecentlyCreated ? $created++ : $updated++;
            }

            $installation->forceFill([
                'last_synced_at' => now(),
                'last_sync_cursor' => $cursor,
                'last_sync_hash' => $cursor ? hash('sha256', $cursor) : null,
                'last_seen_at' => now(),
            ])->save();
        });

        return compact('created', 'updated', 'cursor');
    }
}
