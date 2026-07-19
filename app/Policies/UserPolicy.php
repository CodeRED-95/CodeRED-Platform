<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('users.delete');
    }

    public function restore(User $user, User $model): bool
    {
        return $user->hasPermission('users.restore');
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->hasPermission('users.delete') && $user->hasPermission('users.restore');
    }

    public function manageRoles(User $user, User $model): bool
    {
        return $user->hasPermission('users.manage_roles');
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->hasPermission('users.reset_password');
    }

    public function manageStatus(User $user, User $model): bool
    {
        return $user->hasPermission('users.manage_status');
    }

    public function viewActivity(User $user, User $model): bool
    {
        return $user->hasPermission('users.view_activity');
    }
}
