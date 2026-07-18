<?php

namespace Tests\Feature;

use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Support\AgencyVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AgencyVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_version_service_returns_current_data_version(): void
    {
        Agency::factory()->create(['data_version' => 42]);
        Cache::forget(AgencyVersion::CACHE_KEY);

        $storedVersion = (int) Agency::query()->max('data_version');

        $this->assertSame($storedVersion, AgencyVersion::current());
    }
}
