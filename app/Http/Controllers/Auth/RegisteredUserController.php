<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Core\Auth\AuthenticatedHome;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'pageTitle' => 'Crear cuenta',
        ]);
    }

    public function store(RegisterRequest $request, AuthenticatedHome $home): RedirectResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => trim(preg_replace('/\s+/u', ' ', $data['name'])),
                'email' => mb_strtolower(trim($data['email'])),
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

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->to($home->route($user))->with('success', 'Cuenta creada correctamente.');
    }
}
