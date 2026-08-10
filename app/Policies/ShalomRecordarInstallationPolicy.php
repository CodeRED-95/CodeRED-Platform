<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Modules\ShalomRecordar\Models\ShalomRecordarInstallation;

class ShalomRecordarInstallationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('shalom-recordar.view');
    }

    public function view(User $user, ShalomRecordarInstallation $installation): bool
    {
        return $user->hasPermission('shalom-recordar.view') && ($user->isSuperAdmin() || $installation->user_id === $user->id);
    }

    public function manage(User $user, ShalomRecordarInstallation $installation): bool
    {
        return $user->hasPermission('shalom-recordar.manage') && ($user->isSuperAdmin() || $installation->user_id === $user->id);
    }
}
