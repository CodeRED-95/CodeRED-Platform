<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'app_name'],
            ['group' => 'general', 'value' => 'CodeRED Platform', 'is_public' => true]
        );
    }
}
