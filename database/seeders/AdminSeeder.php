<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\Auth\ConfiguredAdminSyncService;

class AdminSeeder extends Seeder
{
    public function run(ConfiguredAdminSyncService $sync): void
    {
        $sync->sync();
    }
}
