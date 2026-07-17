<?php

namespace App\Modules\Agencies\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AgencyCollection extends ResourceCollection
{
    public function toArray(Request $request): array
    {
        return $this->collection->map(fn ($item) => (new AgencyResource($item))->toArray($request))->all();
    }

    public function with(Request $request): array
    {
        return ['success' => true, 'meta' => $this->resource->toArray()['meta'] ?? []];
    }
}
