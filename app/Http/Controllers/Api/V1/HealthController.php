<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection()->select('select 1');
        } catch (\Throwable) {
            return response()->json([
                'status' => 'degraded',
                'api_version' => (string) config('api.version'),
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'api_version' => (string) config('api.version'),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
