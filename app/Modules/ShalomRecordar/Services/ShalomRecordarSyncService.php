<?php

declare(strict_types=1);

namespace App\Modules\ShalomRecordar\Services;

use App\Core\Audit\AuditLogger;
use App\Models\ApiToken;
use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;
use App\Modules\ShalomRecordar\Models\ShalomRecordarRecord;
use App\Services\ApiTokens\ApiTokenGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ShalomRecordarSyncService
{
    public function __construct(private readonly ApiTokenGenerator $tokenGenerator)
    {
    }

    public function registerInstallation(User $user, array $data, ?Request $request = null): array
    {
        return DB::transaction(function () use ($user, $data, $request): array {
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
                    'last_seen_at' => now(),
                ]
            );

            $previousToken = $installation->syncToken;
            if ($previousToken instanceof ApiToken) {
                $previousToken->forceFill(['revoked_at' => now()])->save();
            }

            $tokenName = 'Shalom Recordar · '.$installation->installation_uuid;
            $token = $this->tokenGenerator->create(
                $user,
                $tokenName,
                ['shalom-recordar:sync', 'shalom-recordar:read-own'],
                365
            );

            $installation->forceFill([
                'sync_token_id' => $token->accessToken->getKey(),
                'last_synced_at' => now(),
                'last_seen_at' => now(),
            ])->save();

            return [
                'installation' => $installation->refresh(),
                'token' => $token->plainTextToken,
            ];
        });
    }

    public function statusForUser(?User $user): array
    {
        if (! $user instanceof User) {
            return [
                'authenticated' => false,
            ];
        }

        return [
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'installations' => $user->shalomRecordarInstallations()->count(),
            'records' => $user->shalomRecordarRecords()->count(),
            'last_synced_at' => $user->shalomRecordarInstallations()->max('last_synced_at'),
        ];
    }

    /**
     * Resuelve la instalación de una consulta de estado.
     *
     * Si viene `installation_uuid` se usa (y se refresca `last_seen_at`). Si no,
     * se deduce del token en uso, que se emite por instalación: la extensión
     * consultaba el estado sin parámetros y recibía un 422, de modo que nunca
     * lograba validar una sesión perfectamente válida.
     *
     * @param  array<string, mixed>  $data
     */
    public function resolveInstallationForRequest(User $user, array $data, ?Request $request = null): ?ShalomRecordarInstallation
    {
        $uuid = isset($data['installation_uuid']) ? trim((string) $data['installation_uuid']) : '';

        if ($uuid !== '') {
            return $this->upsertInstallation($user, [
                'installation_uuid' => $uuid,
                'extension_version' => (string) ($data['extension_version'] ?? ''),
            ] + $data, $request);
        }

        $tokenId = $user->currentAccessToken()?->getKey();

        $installation = $tokenId === null ? null : ShalomRecordarInstallation::query()
            ->where('user_id', $user->id)
            ->where('sync_token_id', $tokenId)
            ->first();

        $installation?->forceFill(['last_seen_at' => now()])->save();

        return $installation;
    }

    /**
     * Registros ya sincronizados por esta instalación (o por el usuario si no
     * se pudo resolver). Alimenta el contador que muestra el popup.
     */
    public function recordsCountFor(User $user, ?ShalomRecordarInstallation $installation): int
    {
        $query = ShalomRecordarRecord::query()->where('user_id', $user->id);

        if ($installation instanceof ShalomRecordarInstallation) {
            $query->where('installation_id', $installation->getKey());
        }

        return $query->count();
    }

    /**
     * Revoca el token con el que se está autenticando la petición actual.
     *
     * Solo toca credenciales: ni los registros locales del navegador ni los ya
     * sincronizados se ven afectados.
     */
    public function revokeCurrentToken(?User $user): bool
    {
        $token = $user?->currentAccessToken();

        if ($token === null) {
            return false;
        }

        $apiToken = ApiToken::query()->find($token->getKey());

        if (! $apiToken instanceof ApiToken || $apiToken->revoked_at !== null) {
            return false;
        }

        $apiToken->forceFill(['revoked_at' => now()])->save();

        return true;
    }

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
        $batchId = null;

        DB::transaction(function () use ($user, $installation, $records, &$created, &$updated, &$cursor, &$batchId): void {
            foreach ($records as $record) {
                $field = trim((string) ($record['field'] ?? ''));
                $value = trim((string) ($record['value'] ?? ''));
                $recordedAt = (string) ($record['timestamp'] ?? now()->toISOString());
                $externalRecordId = trim((string) ($record['record_id'] ?? $record['id'] ?? ''));
                $cursor = (string) ($record['cursor'] ?? $cursor ?? $recordedAt);
                $batchId = (string) ($record['batch_id'] ?? $batchId ?? ($record['cursor'] ?? $cursor ?? $recordedAt));
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
                        'sync_batch_id' => $batchId,
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

        return compact('created', 'updated', 'cursor', 'batchId');
    }

    public function deleteSyncBatch(ShalomRecordarInstallation $installation, string $batchId, AuditLogger $audit): int
    {
        return DB::transaction(function () use ($installation, $batchId, $audit): int {
            $records = ShalomRecordarRecord::query()
                ->where('installation_id', $installation->id)
                ->where('sync_batch_id', $batchId)
                ->get();

            $count = $records->count();
            abort_if($count === 0, 404, 'Batch de sincronización no encontrado.');

            foreach ($records as $record) {
                $audit->log($record, 'shalom_recordar_sync_batch_deleted', [
                    'installation_id' => $installation->id,
                    'sync_batch_id' => $batchId,
                ], [], ['sync_batch_id']);
                $record->delete();
            }

            return $count;
        });
    }

    public function deleteInstallationSyncs(ShalomRecordarInstallation $installation, AuditLogger $audit): int
    {
        return DB::transaction(function () use ($installation, $audit): int {
            $records = ShalomRecordarRecord::query()->where('installation_id', $installation->id)->get();
            $count = $records->count();

            foreach ($records as $record) {
                $audit->log($record, 'shalom_recordar_record_deleted', [
                    'installation_id' => $installation->id,
                ], [], ['installation_id']);
                $record->delete();
            }

            $audit->log($installation, 'shalom_recordar_installation_syncs_deleted', [
                'installation_id' => $installation->id,
                'installation_uuid' => $installation->installation_uuid,
                'records' => $count,
            ], [], ['installation_id', 'installation_uuid', 'records']);

            return $count;
        });
    }

    public function revokeInstallationToken(ShalomRecordarInstallation $installation, AuditLogger $audit): void
    {
        DB::transaction(function () use ($installation, $audit): void {
            $token = $installation->syncToken;
            if ($token instanceof ApiToken && $token->revoked_at === null) {
                $audit->log($token, 'shalom_recordar_installation_token_revoked', [
                    'installation_id' => $installation->id,
                    'installation_uuid' => $installation->installation_uuid,
                ], [], ['installation_id', 'installation_uuid']);
                $token->forceFill(['revoked_at' => now()])->save();
            }
        });
    }

    public function deleteInstallation(ShalomRecordarInstallation $installation, AuditLogger $audit): void
    {
        DB::transaction(function () use ($installation, $audit): void {
            $this->revokeInstallationToken($installation, $audit);
            $this->deleteInstallationSyncs($installation, $audit);
            $audit->log($installation, 'shalom_recordar_installation_deleted', [
                'installation_id' => $installation->id,
                'installation_uuid' => $installation->installation_uuid,
            ], [], ['installation_id', 'installation_uuid']);
            $installation->delete();
        });
    }

}
