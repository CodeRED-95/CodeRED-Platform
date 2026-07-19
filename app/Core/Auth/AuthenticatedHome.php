<?php

namespace App\Core\Auth;

use App\Models\User;

final class AuthenticatedHome
{
    public function route(User $user): string
    {
        if ($user->hasPermission('dashboard.view')) {
            return route('dashboard');
        }

        if ($user->hasPermission('agencies.view')) {
            return route('admin.agencies.index');
        }

        return route('profile.show');
    }
}
