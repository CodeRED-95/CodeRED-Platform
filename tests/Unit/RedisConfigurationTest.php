<?php

namespace Tests\Unit;

use Tests\TestCase;

class RedisConfigurationTest extends TestCase
{
    public function test_redis_credentials_are_empty_when_no_auth_is_used(): void
    {
        $this->assertSame('phpredis', config('database.redis.client'));
        $this->assertSame('redis', config('database.redis.default.host'));
        $this->assertNull(config('database.redis.default.username'));
        $this->assertNull(config('database.redis.default.password'));
        $this->assertSame('0', (string) config('database.redis.default.database'));
        $this->assertSame('1', (string) config('database.redis.cache.database'));
    }
}
