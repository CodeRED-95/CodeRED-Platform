<?php

namespace App\Modules\Ruc\Http\Controllers;

use App\Modules\Ruc\Http\Requests\RucSearchRequest;
use App\Modules\Ruc\Http\Resources\RucResource;
use App\Modules\Ruc\Models\RucRecord;

class RucSearchApiController
{
    public function __invoke(RucSearchRequest $request)
    {
        $term = trim($request->validated('razon_social'));
        $records = RucRecord::query()->whereRaw('razon_social ILIKE ?', ['%'.$term.'%'])
            ->orderBy('razon_social')->orderBy('ruc')->paginate((int) ($request->validated('per_page') ?? 20));

        return response()->json(['success' => true, 'data' => RucResource::collection($records->items())->resolve($request), 'meta' => ['current_page' => $records->currentPage(), 'per_page' => $records->perPage(), 'total' => $records->total(), 'last_page' => $records->lastPage()]]);
    }
}
