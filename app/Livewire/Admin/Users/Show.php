<?php

namespace App\Livewire\Admin\Users;

use App\Models\ActivityLog;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        Gate::authorize('view', $user);
        $this->user = $user->load(['roles.permissions', 'creator', 'updater']);
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $canViewActivity = app(UserPolicy::class)->viewActivity($actor, $this->user);
        $activity = $canViewActivity
            ? ActivityLog::query()
                ->with('actor')
                ->where('auditable_type', User::class)
                ->where('auditable_id', $this->user->id)
                ->latest('created_at')
                ->limit(25)
                ->get()
            : new Collection;

        return view('livewire.admin.users.show', [
            'activity' => $activity,
            'canViewActivity' => $canViewActivity,
            'effectivePermissions' => $this->user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('slug')->all())
                ->unique()
                ->sort()
                ->values(),
        ])->layout('layouts.app', ['pageTitle' => $this->user->name]);
    }
}
