<?php

namespace App\Modules\Ruc\Policies;

use App\Models\User;
use App\Modules\Ruc\Models\RucImport;

class RucImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ruc.view-imports');
    }

    public function view(User $user, RucImport $import): bool
    {
        return $user->can('ruc.view-imports');
    }

    public function create(User $user): bool
    {
        return $user->can('ruc.import');
    }

    public function pause(User $user, RucImport $import): bool
    {
        return $user->can('ruc.pause-import') && $import->canCancel();
    }

    public function resume(User $user, RucImport $import): bool
    {
        return $user->can('ruc.resume-import') && $import->canResume();
    }

    public function cancel(User $user, RucImport $import): bool
    {
        return $user->can('ruc.cancel-import') && $import->canCancel();
    }

    public function rollback(User $user, RucImport $import): bool
    {
        return $user->can('ruc.rollback-import') && $import->canRollback();
    }

    public function viewErrors(User $user, RucImport $import): bool
    {
        return $user->can('ruc.view-import-errors');
    }
}
