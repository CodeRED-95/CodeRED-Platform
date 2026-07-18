<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Agencies\Models\Agency;

class AgencyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('agencies.view');
    }

    public function view(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agencies.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('agencies.create');
    }

    public function update(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agencies.update');
    }

    public function delete(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agencies.delete');
    }

    public function restore(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agencies.restore');
    }

    public function manageStatus(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agencies.manage_status');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('agencies.import');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('agencies.export');
    }

    public function viewHistory(User $user, Agency $agency): bool
    {
        return $user->hasPermission('agencies.view_history');
    }
}
