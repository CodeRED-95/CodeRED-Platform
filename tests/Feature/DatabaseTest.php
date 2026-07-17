<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTest extends TestCase
{
    public function test_database_connection_is_available(): void
    {
        $result = DB::select('select 1 as one');

        $this->assertSame(1, (int) $result[0]->one);
    }
}
