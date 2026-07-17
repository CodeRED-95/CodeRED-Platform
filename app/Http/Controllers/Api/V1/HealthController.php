<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $postgres = true;
        $redis = true;

        try {
            DB::connection()->select('select 1');
        } catch (\Throwable) {
            $postgres = false;
        }

        try {
            Redis::connection()->ping();
        } catch (\Throwable) {
            $redis = false;
        }

        return response()->json([
            'status' => $postgres && $redis ? 'ok' : 'degraded',
            'database' => $postgres,
            'redis' => $redis,
            'version' => config('app.version', '1.0.0'),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
