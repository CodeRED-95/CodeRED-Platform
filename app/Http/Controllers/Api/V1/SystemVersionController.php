<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class SystemVersionController
{
    public function __invoke(): JsonResponse
    {
        $version = (string) config('version.current');

        return response()->json([
            'success' => true,
            'data' => [
                'version' => $version,
                'api_version' => config('version.api'),
                'environment' => config('app.env'),
            ],
        ])->header('X-Application-Version', $version);
    }
}
