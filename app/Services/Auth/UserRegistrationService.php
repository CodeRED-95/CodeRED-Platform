<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UserRegistrationService
{
    /**
     * Creates the same pending account for the web form and official clients.
     * Passwords are hashed here and the caller is responsible for issuing OTP.
     *
     * @param  array{name:string,email:string,password:string}  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => trim(preg_replace('/\s+/u', ' ', $data['name'])),
                'email' => mb_strtolower(trim($data['email'])),
                'email_verification_required' => true,
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'is_active' => true,
            ]);

            $role = Role::query()->firstOrCreate([
                'slug' => 'viewer',
            ], [
                'name' => 'Consulta',
                'description' => 'Consulta de agencias y sincronizaciones propias',
                'is_system' => true,
            ]);

            collect([
                ['slug' => 'agencies.view', 'name' => 'Ver agencias'],
                ['slug' => 'agencies.map', 'name' => 'Ver mapa de agencias'],
                ['slug' => 'shalom-recordar.sync', 'name' => 'Sincronizar mis datos de Shalom Recordar'],
                ['slug' => 'shalom-recordar.view-own', 'name' => 'Ver mis sincronizaciones de Shalom Recordar'],
            ])->each(fn (array $permission) => Permission::query()->firstOrCreate(['slug' => $permission['slug']], $permission));

            $permissionIds = Permission::query()
                ->whereIn('slug', ['agencies.view', 'agencies.map', 'shalom-recordar.sync', 'shalom-recordar.view-own'])
                ->pluck('id')
                ->all();
            $role->permissions()->syncWithoutDetaching($permissionIds);
            $user->roles()->syncWithoutDetaching([$role->id]);

            return $user;
        });
    }
}
