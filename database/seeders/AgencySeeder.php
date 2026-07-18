<?php

namespace Database\Seeders;

use App\Modules\Agencies\Models\Agency;
use Illuminate\Database\Seeder;

class AgencySeeder extends Seeder
{
    public function run(): void
    {
        if (Agency::query()->count() > 0) {
            return;
        }

        Agency::factory()->count(25)->create();
    }
}
