<?php

namespace App\Livewire\Admin\Users;

use App\Core\Audit\AuditLogger;
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
    private const ALLOWED_ROLES = ['super-admin', 'viewer', 'editor', 'store-agency-user', 'store-supervisor', 'store-admin'];

    /** @var list<string> */
    private const ACCESS_ROLES = ['acceso-platform', 'acceso-store', 'acceso-mobile', 'acceso-desktop', 'acceso-ruc', 'acceso-dni'];

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
            $this->roles = $user->roles()->whereIn('slug', self::ALLOWED_ROLES)->pluck('slug')->all();
            $this->status = $user->status ?? ($user->is_active ? 'active' : 'inactive');
            $this->email_verified = $user->email_verified_at !== null;
            $this->must_change_password = (bool) $user->must_change_password;
        }
    }

    public function save(UserSecurityService $security, AuditLogger $auditLogger): void
    {
        $validated = $this->validate($this->rules());
        $actor = auth()->user();

        DB::transaction(function () use ($actor, $auditLogger, $security, $validated): void {
            $previousRoles = $this->managedUser?->roles()->pluck('slug')->sort()->values()->all() ?? [];
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
                $emailChanged = $this->managedUser->email !== $validated['email'];
                $this->managedUser->fill($payload);
                if ($emailChanged || $this->email_verified !== ($this->managedUser->email_verified_at !== null)) {
                    $this->managedUser->email_verified_at = $validated['email_verified'] ? now() : null;
                }
                if ($validated['password']) {
                    $this->managedUser->password = Hash::make($validated['password']);
                }
                $this->managedUser->save();
            }

            $security->canAssignRoles($actor, $this->managedUser, $validated['roles']);
            $roleIds = Role::query()->whereIn('slug', $validated['roles'])->pluck('id')->all();
            if ($this->mode === 'edit' && $this->managedUser instanceof User) {
                $roleIds = array_merge($roleIds, $this->managedUser->roles()->whereIn('slug', self::ACCESS_ROLES)->pluck('id')->all());
            }
            $this->managedUser->roles()->sync(array_values(array_unique($roleIds)));

            $newRoles = $this->managedUser->roles()->pluck('slug')->sort()->values()->all();
            if ($previousRoles !== $newRoles) {
                $auditLogger->log(
                    $this->managedUser,
                    'roles_updated',
                    ['roles' => $previousRoles],
                    ['roles' => $newRoles],
                    ['roles'],
                );
            }
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
            'roles' => ['array', $this->mode === 'create' ? 'min:1' : 'nullable'],
            'roles.*' => ['string', Rule::in(self::ALLOWED_ROLES)],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'email_verified' => ['boolean'],
            'must_change_password' => ['boolean'],
        ];
    }

    public function render()
    {
        return view('livewire.admin.users.form', [
            'availableRoles' => Role::query()->whereIn('slug', self::ALLOWED_ROLES)->orderBy('name')->get(['slug', 'name']),
        ])->layout('layouts.app', ['pageTitle' => $this->mode === 'edit' ? 'Editar usuario' : 'Nuevo usuario']);
    }
}
