<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NginxCompressionConfigurationTest extends TestCase
{
    public function test_nginx_enables_compression_for_api_responses(): void
    {
        $configuration = file_get_contents(dirname(__DIR__, 2).'/docker/nginx/default.conf');

        $this->assertIsString($configuration);
        $this->assertStringContainsString('gzip on;', $configuration);
        $this->assertStringContainsString('gzip_vary on;', $configuration);
        $this->assertStringContainsString('gzip_proxied any;', $configuration);
        $this->assertStringContainsString('application/json', $configuration);
    }
}
