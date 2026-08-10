<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class ConfiguredAdminSyncService
{
    /**
     * @return array{user: User, created: bool}
     */
    public function sync(): array
    {
        $name = trim((string) (getenv('DEV_ADMIN_NAME') ?: config('codered.dev_admin.name', '')));
        $email = $this->normalizeEmail((string) (getenv('DEV_ADMIN_EMAIL') ?: config('codered.dev_admin.email', '')));
        $password = (string) (getenv('DEV_ADMIN_PASSWORD') ?: config('codered.dev_admin.password', ''));

        if ($name === '' || $email === '' || $password === '') {
            throw new InvalidArgumentException('Las variables DEV_ADMIN_NAME, DEV_ADMIN_EMAIL y DEV_ADMIN_PASSWORD son obligatorias.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('DEV_ADMIN_EMAIL debe ser un correo electrónico válido.');
        }

        if (App::isProduction() && str_starts_with($password, 'CHANGE_THIS_')) {
            throw new InvalidArgumentException('La contraseña del administrador de desarrollo no puede usar un valor de ejemplo en producción.');
        }

        $roleId = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Administrador', 'description' => 'Acceso total', 'is_system' => true],
        )->id;
        $user = User::query()->withTrashed()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first();
        $created = false;

        if (! $user) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'status' => 'active',
                'is_active' => true,
            ]);
            $created = true;
        } else {
            $user->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'status' => 'active',
                'is_active' => true,
                'deleted_at' => null,
            ])->save();
        }

        $user->roles()->syncWithoutDetaching([$roleId]);

        return ['user' => $user->fresh(), 'created' => $created];
    }

    private function normalizeEmail(string $email): string
    {
        $email = trim($email);

        if (str_starts_with(strtolower($email), 'mailto:')) {
            $email = substr($email, 7);
        }

        return trim($email);
    }
}
