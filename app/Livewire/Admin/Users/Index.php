<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
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
    public string $withTrashed = '';

    #[Url]
    public int $perPage = 15;

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updating($name): void
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

    public function render()
    {
        $query = User::query()
            ->with(['roles', 'creator', 'updater'])
            ->search($this->search)
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->role !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('slug', $this->role)))
            ->when($this->verified === '1', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($this->verified === '0', fn ($q) => $q->whereNull('email_verified_at'))
            ->when($this->access === '1', fn ($q) => $q->whereNotNull('last_login_at'))
            ->when($this->access === '0', fn ($q) => $q->whereNull('last_login_at'));

        if ($this->withTrashed === '1') {
            $query->withTrashed();
        }

        $allowed = ['name', 'email', 'status', 'last_login_at', 'created_at'];
        if (! in_array($this->sortField, $allowed, true)) {
            $this->sortField = 'created_at';
        }

        $users = $query->orderBy($this->sortField, $this->sortDirection)->paginate($this->perPage);

        return view('livewire.admin.users.index', [
            'users' => $users,
            'roles' => \App\Models\Role::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ])->layout('layouts.app', ['pageTitle' => 'Usuarios']);
    }
}
