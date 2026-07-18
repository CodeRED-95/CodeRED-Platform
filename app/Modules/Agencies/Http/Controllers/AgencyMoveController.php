<?php

namespace App\Modules\Agencies\Http\Controllers;

use App\Http\Requests\Agencies\MoveAgencyRequest;
use App\Modules\Agencies\Actions\ApplyAgencyMoveAction;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Http\JsonResponse;

class AgencyMoveController
{
    public function __invoke(MoveAgencyRequest $request, Agency $agency, ApplyAgencyMoveAction $action): JsonResponse
    {
        $updated = $action->execute(
            $agency,
            $request->validated(),
            $request->user()?->id,
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $updated,
            'meta' => [],
        ]);
    }
}
