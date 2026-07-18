<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiRoutePrefixTest extends TestCase
{
    public function test_agency_routes_do_not_duplicate_api_prefix(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->pluck('uri')
            ->filter(fn (string $uri) => str_contains($uri, 'agencies'))
            ->values()
            ->all();

        $this->assertContains('api/v1/agencies', $routes);
        $this->assertContains('api/v1/agencies/search', $routes);
        $this->assertContains('api/v1/agencies/version', $routes);
        $this->assertContains('api/v1/agencies/snapshot', $routes);
        $this->assertContains('api/v1/agencies/{code}', $routes);
        $this->assertNotContains('api/api/v1/agencies', $routes);
    }
}
