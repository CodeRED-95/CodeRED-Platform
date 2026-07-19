<?php

namespace App\Livewire\Admin\Users;

use App\Models\Role;
use App\Models\User;
use App\Modules\Users\Services\UserSecurityService;
use App\Policies\UserPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $verified = '';

    #[Url]
    public string $access = '';

    #[Url]
    public string $trash = '';

    #[Url]
    public int $perPage = 15;

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';

            return;
        }

        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    public function deleteUser(int $userId, UserSecurityService $security): void
    {
        $target = User::query()->findOrFail($userId);
        $actor = $this->actor();
        $this->authorizeUserPolicy('delete', $actor, $target);
        $security->canDelete($actor, $target);
        $target->delete();

        $this->dispatch('toast', type: 'success', message: 'El usuario se movió a la papelera.');
    }

    public function restoreUser(int $userId, UserSecurityService $security): void
    {
        $target = User::onlyTrashed()->findOrFail($userId);
        $actor = $this->actor();
        $this->authorizeUserPolicy('restore', $actor, $target);
        $security->canManage($actor, $target);
        $target->restore();

        $this->dispatch('toast', type: 'success', message: 'El usuario fue restaurado.');
    }

    public function forceDeleteUser(int $userId, UserSecurityService $security): void
    {
        $target = User::onlyTrashed()->findOrFail($userId);
        $actor = $this->actor();
        $this->authorizeUserPolicy('forceDelete', $actor, $target);
        $security->canDelete($actor, $target);
        $target->forceDelete();

        $this->dispatch('toast', type: 'success', message: 'El usuario fue eliminado definitivamente.');
    }

    public function render(): View
    {
        $query = User::query()
            ->with(['roles', 'creator', 'updater'])
            ->when($this->trash === 'only', fn ($query) => $query->onlyTrashed())
            ->when($this->trash === 'with', fn ($query) => $query->withTrashed())
            ->search($this->search)
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->role !== '', fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('slug', $this->role)))
            ->when($this->verified === '1', fn ($query) => $query->whereNotNull('email_verified_at'))
            ->when($this->verified === '0', fn ($query) => $query->whereNull('email_verified_at'))
            ->when($this->access === '1', fn ($query) => $query->whereNotNull('last_login_at'))
            ->when($this->access === '0', fn ($query) => $query->whereNull('last_login_at'));

        $allowed = ['name', 'email', 'status', 'last_login_at', 'created_at'];
        if (! in_array($this->sortField, $allowed, true)) {
            $this->sortField = 'created_at';
        }

        $users = $query->orderBy($this->sortField, $this->sortDirection)->paginate($this->perPage);
        $actor = $this->actor();
        $policy = app(UserPolicy::class);

        return view('livewire.admin.users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'trashCount' => User::onlyTrashed()->count(),
            'canDeleteUsers' => $policy->delete($actor, $actor),
            'canRestoreUsers' => $policy->restore($actor, $actor),
            'canForceDeleteUsers' => $policy->forceDelete($actor, $actor),
        ])->layout('layouts.app', ['pageTitle' => 'Usuarios']);
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private function authorizeUserPolicy(string $ability, User $actor, User $target): void
    {
        $policy = app(UserPolicy::class);
        $allowed = match ($ability) {
            'delete' => $policy->delete($actor, $target),
            'restore' => $policy->restore($actor, $target),
            'forceDelete' => $policy->forceDelete($actor, $target),
            default => false,
        };

        if (! $allowed) {
            throw new AuthorizationException('No tienes autorización para realizar esta acción.');
        }
    }
}
