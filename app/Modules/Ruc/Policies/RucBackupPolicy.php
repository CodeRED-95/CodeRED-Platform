<?php

declare(strict_types=1);

namespace App\Modules\Ruc\Policies;

use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;

class RucBackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ruc.backup.view');
    }

    public function download(User $user, RucBackup $backup): bool
    {
        return $user->hasPermission('ruc.backup.download');
    }

    public function upload(User $user): bool
    {
        return $user->hasPermission('ruc.backup.create');
    }

    public function restore(User $user, RucBackup $backup): bool
    {
        return $user->hasPermission('ruc.backup.restore');
    }

    public function delete(User $user, RucBackup $backup): bool
    {
        return $user->hasPermission('ruc.backup.delete');
    }
}
