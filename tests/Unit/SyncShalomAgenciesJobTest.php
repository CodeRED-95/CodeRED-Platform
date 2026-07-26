<?php

namespace Tests\Unit;

use App\Modules\Agencies\Jobs\SyncShalomAgenciesJob;
use ReflectionMethod;
use Tests\TestCase;

class SyncShalomAgenciesJobTest extends TestCase
{
    public function test_nullable_float_turns_empty_strings_into_null(): void
    {
        $job = new SyncShalomAgenciesJob(1, 'chosen.txt');
        $method = new ReflectionMethod($job, 'nullableFloat');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($job, ''));
        $this->assertNull($method->invoke($job, ' '));
        $this->assertNull($method->invoke($job, 'null'));
        $this->assertNull($method->invoke($job, null));
        $this->assertSame(-18.062945787541, $method->invoke($job, '-18.062945787541'));
    }
}
