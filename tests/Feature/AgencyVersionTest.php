<?php

namespace Tests\Feature;

use App\Modules\Agencies\Support\AgencyVersion;
use Tests\TestCase;

class AgencyVersionTest extends TestCase
{
    public function test_version_service_returns_integer(): void
    {
        $this->assertIsInt(AgencyVersion::current());
    }
}
