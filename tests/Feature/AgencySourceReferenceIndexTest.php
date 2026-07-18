<?php

namespace Tests\Feature;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgencySourceReferenceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_github_gist_and_source_reference_must_be_unique_together(): void
    {
        Agency::factory()->create([
            'source' => 'github_gist',
            'source_reference' => '3',
        ]);

        $this->expectException(QueryException::class);

        Agency::factory()->create([
            'source' => 'github_gist',
            'source_reference' => '3',
        ]);
    }

    public function test_same_source_reference_is_allowed_for_other_sources(): void
    {
        Agency::factory()->create([
            'source' => 'github_gist',
            'source_reference' => '3',
        ]);

        $agency = Agency::factory()->create([
            'source' => 'manual',
            'source_reference' => '3',
        ]);

        $this->assertSame('manual', $agency->source);
        $this->assertSame('3', $agency->source_reference);
    }

    public function test_null_source_reference_can_repeat_multiple_times(): void
    {
        $first = Agency::factory()->create([
            'source_reference' => null,
        ]);

        $second = Agency::factory()->create([
            'source_reference' => null,
        ]);

        $this->assertNull($first->source_reference);
        $this->assertNull($second->source_reference);
    }
}
