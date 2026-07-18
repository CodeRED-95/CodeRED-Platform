<?php

namespace App\Modules\Users\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UserSecurityService
{
    public function canManage(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new AuthorizationException('No puedes realizar esta acción sobre tu propia cuenta.');
        }

        if ($target->hasRole('super-admin') && ! $actor->hasRole('super-admin')) {
            throw new AuthorizationException('Solo un superadministrador puede administrar esta cuenta.');
        }
    }

    public function canChangeStatus(User $actor, User $target): void
    {
        $this->canManage($actor, $target);

        if ($actor->is($target)) {
            throw new AuthorizationException('No puedes cambiar el estado de tu propia cuenta.');
        }

        if ($target->hasRole('super-admin') && $this->isLastSuperAdmin($target)) {
            throw new AuthorizationException('No puedes desactivar al último superadministrador activo.');
        }
    }

    public function canDelete(User $actor, User $target): void
    {
        $this->canManage($actor, $target);

        if ($actor->is($target)) {
            throw new AuthorizationException('No puedes eliminar tu propia cuenta.');
        }

        if ($target->hasRole('super-admin') && $this->isLastSuperAdmin($target)) {
            throw new AuthorizationException('No puedes eliminar al último superadministrador.');
        }
    }

    public function canAssignRoles(User $actor, User $target, array $roleSlugs): void
    {
        $this->canManage($actor, $target);

        if (! $actor->hasRole('super-admin') && in_array('super-admin', $roleSlugs, true)) {
            throw new AuthorizationException('No puedes asignar el rol super-admin.');
        }

        if ($actor->is($target) && ! in_array('super-admin', $roleSlugs, true) && $target->hasRole('super-admin')) {
            throw new AuthorizationException('No puedes quitarte tu propio rol super-admin.');
        }
    }

    public function isLastSuperAdmin(User $user): bool
    {
        if (! $user->hasRole('super-admin')) {
            return false;
        }

        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))
            ->where('id', '!=', $user->id)
            ->where('status', 'active')
            ->exists() === false;
    }
}
