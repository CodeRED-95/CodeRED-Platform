<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $postgres = true;
        $redis = true;
        $cache = true;
        $queue = true;

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

        try {
            Cache::put('codered:health', 'ok', 10);
            $cache = Cache::get('codered:health') === 'ok';
            Cache::forget('codered:health');
        } catch (\Throwable) {
            $cache = false;
        }

        try {
            $queue = Queue::connection()->getName() !== '';
        } catch (\Throwable) {
            $queue = false;
        }

        return response()->json([
            'status' => $postgres && $redis && $cache && $queue ? 'ok' : 'degraded',
            'database' => $postgres,
            'redis' => $redis,
            'cache' => $cache,
            'queue' => $queue,
            'version' => config('app.version', '1.0.0'),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
