<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Agencies\Models\Agency;

class AgencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'agencies.view');
    }

    public function view(User $user, Agency $agency): bool
    {
        return $this->can($user, 'agencies.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'agencies.create');
    }

    public function update(User $user, Agency $agency): bool
    {
        return $this->can($user, 'agencies.update');
    }

    public function delete(User $user, Agency $agency): bool
    {
        return $this->can($user, 'agencies.delete');
    }

    public function restore(User $user, Agency $agency): bool
    {
        return $this->can($user, 'agencies.restore');
    }

    public function manageStatus(User $user, Agency $agency): bool
    {
        return $this->can($user, 'agencies.manage_status');
    }

    public function import(User $user): bool
    {
        return $this->can($user, 'agencies.import');
    }

    public function export(User $user): bool
    {
        return $this->can($user, 'agencies.export');
    }

    public function viewHistory(User $user, Agency $agency): bool
    {
        return $this->can($user, 'agencies.view_history');
    }

    private function can(User $user, string $permission): bool
    {
        return $user->roles()->whereHas('permissions', fn ($query) => $query->where('slug', $permission))->exists();
    }
}
