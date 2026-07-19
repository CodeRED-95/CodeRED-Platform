<?php

namespace App\Modules\Agencies\Services;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencySyncChange;

final class AgencySyncChangeRecorder
{
    public function __construct(private readonly AgencySyncPayload $payload) {}

    public function upsert(Agency $agency): AgencySyncChange
    {
        return AgencySyncChange::query()->create([
            'agency_internal_id' => $agency->getKey(),
            'external_id' => $agency->external_id,
            'code' => $agency->code,
            'operation' => 'upsert',
            'payload' => $this->payload->fromAgency($agency),
            'schema_version' => (int) config('api.agency_schema_version'),
            'changed_at' => $agency->updated_at ?? now(),
        ]);
    }

    public function delete(Agency $agency): AgencySyncChange
    {
        return AgencySyncChange::query()->create([
            'agency_internal_id' => $agency->getKey(),
            'external_id' => $agency->external_id,
            'code' => $agency->code,
            'operation' => 'delete',
            'payload' => null,
            'schema_version' => (int) config('api.agency_schema_version'),
            'changed_at' => $agency->deleted_at ?? now(),
        ]);
    }
}
