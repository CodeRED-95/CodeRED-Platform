<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class SystemVersionController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'version' => config('version.current'),
                'api_version' => config('version.api'),
                'environment' => config('app.env'),
            ],
        ]);
    }
}
