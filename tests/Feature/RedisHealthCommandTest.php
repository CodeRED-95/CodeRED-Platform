<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisHealthCommandTest extends TestCase
{
    public function test_health_redis_command_writes_reads_and_deletes_temporary_key(): void
    {
        $originalDriver = config('cache.default');
        $originalQueue = config('queue.default');
        $originalSession = config('session.driver');
        $originalRedisClient = config('database.redis.client');

        $exitCode = Artisan::call('health:redis');

        $this->assertSame(0, $exitCode);
        $this->assertSame($originalDriver, config('cache.default'));
        $this->assertSame($originalQueue, config('queue.default'));
        $this->assertSame($originalSession, config('session.driver'));
        $this->assertSame($originalRedisClient, config('database.redis.client'));

        $this->assertNotFalse(Redis::connection()->ping());
        $this->assertNull(Cache::get('codered_health_test_non_existent'));
    }
}
