<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $devAdminName = trim((string) env('DEV_ADMIN_NAME', ''));
        $devAdminEmail = trim((string) env('DEV_ADMIN_EMAIL', ''));
        $devAdminPassword = (string) env('DEV_ADMIN_PASSWORD', '');

        if ($devAdminName === '' || $devAdminEmail === '' || $devAdminPassword === '') {
            throw new InvalidArgumentException('Las variables DEV_ADMIN_NAME, DEV_ADMIN_EMAIL y DEV_ADMIN_PASSWORD son obligatorias.');
        }

        if (App::isProduction() && str_starts_with($devAdminPassword, 'CHANGE_THIS_')) {
            throw new InvalidArgumentException('La contraseña del administrador de desarrollo no puede usar un valor de ejemplo en producción.');
        }

        $roles = Role::query()->pluck('id', 'slug');

        $devUser = User::query()->updateOrCreate(
            ['email' => $devAdminEmail],
            [
                'name' => $devAdminName,
                'password' => Hash::make($devAdminPassword),
                'is_active' => true,
            ]
        );

        $devUser->roles()->syncWithoutDetaching(
            array_filter([
                $roles->get('super-admin'),
                $roles->get('admin'),
            ])
        );
    }
}
