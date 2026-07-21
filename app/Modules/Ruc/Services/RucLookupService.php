<?php

namespace App\Modules\Ruc\Services;

use App\Modules\Ruc\Data\RucData;
use App\Modules\Ruc\Models\RucRecord;
use Illuminate\Support\Facades\Cache;

class RucLookupService
{
    public function find(string $ruc): array
    {
        $key = 'ruc:lookup:'.$ruc;
        if (config('ruc.cache_enabled') && ($cached = Cache::get($key)) !== null) {
            return ['data' => new RucData(...$cached), 'source' => 'cache', 'cached' => true];
        }
        $record = RucRecord::query()->where('ruc', $ruc)->first();
        if ($record === null) {
            return ['data' => null, 'source' => 'internal', 'cached' => false];
        }
        $data = RucData::fromModel($record);
        if (config('ruc.cache_enabled')) {
            Cache::put($key, [$data->ruc, $data->razonSocial, $data->estado, $data->condicion, $data->ubigeo, $data->direccion, $data->departamento, $data->provincia, $data->distrito], max(1, (int) config('ruc.cache_ttl')));
        }

        return ['data' => $data, 'source' => 'internal', 'cached' => false];
    }
}
