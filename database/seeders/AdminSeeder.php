<?php

namespace Database\Seeders;

use App\Services\Auth\ConfiguredAdminSyncService;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(ConfiguredAdminSyncService $sync): void
    {
        $sync->sync();
    }
}
