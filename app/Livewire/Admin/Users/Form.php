<?php

namespace App\Livewire\Admin\Users;

use App\Models\Role;
use App\Models\User;
use App\Modules\Users\Services\UserSecurityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?User $managedUser = null;

    public string $mode = 'create';

    public string $name = '';

    public string $email = '';

    public ?string $password = null;

    public ?string $password_confirmation = null;

    public array $roles = [];

    public string $status = 'active';

    public bool $email_verified = false;

    public bool $must_change_password = false;

    public function mount(User|int|null $user = null): void
    {
        if (is_int($user)) {
            $user = User::query()->findOrFail($user);
        }

        if ($user !== null && ! $user->exists) {
            $user = null;
        }

        $this->managedUser = $user;
        $this->mode = $user ? 'edit' : 'create';
        Gate::authorize($user ? 'update' : 'create', $user ?? User::class);

        if ($user) {
            $this->name = $user->name;
            $this->email = $user->email;
            $this->roles = $user->roles()->pluck('slug')->all();
            $this->status = $user->status ?? ($user->is_active ? 'active' : 'inactive');
            $this->email_verified = $user->email_verified_at !== null;
            $this->must_change_password = (bool) $user->must_change_password;
        }
    }

    public function save(UserSecurityService $security): void
    {
        $validated = $this->validate($this->rules());
        $actor = auth()->user();

        DB::transaction(function () use ($actor, $security, $validated): void {
            $payload = [
                'name' => trim(preg_replace('/\s+/u', ' ', $validated['name'])),
                'email' => mb_strtolower(trim($validated['email'])),
                'status' => $validated['status'],
                'must_change_password' => (bool) $validated['must_change_password'],
                'is_active' => $validated['status'] === 'active',
            ];

            if ($this->mode === 'create') {
                $payload['password'] = Hash::make($validated['password']);
                $payload['email_verified_at'] = $validated['email_verified'] ? now() : null;
                $this->managedUser = User::query()->create($payload);
            } else {
                $security->canManage($actor, $this->managedUser);
                $this->managedUser->fill($payload);
                if ($this->email !== $validated['email']) {
                    $this->managedUser->email_verified_at = $validated['email_verified'] ? now() : null;
                }
                if ($validated['password']) {
                    $this->managedUser->password = Hash::make($validated['password']);
                }
                $this->managedUser->save();
            }

            $security->canAssignRoles($actor, $this->managedUser, $validated['roles']);
            $this->managedUser->roles()->sync(Role::query()->whereIn('slug', $validated['roles'])->pluck('id')->all());
        });

        session()->flash('success', $this->mode === 'edit' ? 'Usuario actualizado correctamente.' : 'Usuario creado correctamente.');
        $this->redirectRoute('admin.users.show', $this->managedUser);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->managedUser?->id)],
            'password' => [$this->mode === 'create' ? 'required' : 'nullable', 'confirmed', 'min:12'],
            'password_confirmation' => [$this->mode === 'create' ? 'required' : 'nullable'],
            'roles' => ['array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'slug')],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'email_verified' => ['boolean'],
            'must_change_password' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.admin.users.form', [
            'availableRoles' => Role::query()->orderBy('name')->get(['slug', 'name']),
        ])->layout('layouts.app', ['pageTitle' => $this->mode === 'edit' ? 'Editar usuario' : 'Nuevo usuario']);
    }
}
