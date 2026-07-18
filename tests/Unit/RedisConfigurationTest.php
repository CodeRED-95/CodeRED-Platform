<?php

namespace Tests\Unit;

use Tests\TestCase;

class RedisConfigurationTest extends TestCase
{
    public function test_redis_credentials_are_empty_when_no_auth_is_used(): void
    {
        $this->assertSame('phpredis', env('REDIS_CLIENT'));
        $this->assertSame('redis', env('REDIS_HOST'));
        $this->assertSame('', (string) env('REDIS_USERNAME', ''));
        $this->assertSame('', (string) env('REDIS_PASSWORD', ''));
        $this->assertSame('0', (string) env('REDIS_DB', '0'));
        $this->assertSame('1', (string) env('REDIS_CACHE_DB', '1'));
    }
}
