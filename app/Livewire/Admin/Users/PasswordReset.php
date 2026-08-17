<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use App\Modules\Users\Services\UserSecurityService;
use App\Services\Auth\AuthAuditor;
use App\Services\Auth\ClientSessionManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class PasswordReset extends Component
{
    public User $user;

    public string $temporaryPassword = '';

    public bool $mustChangePassword = true;

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->temporaryPassword = Str::password(16);
    }

    public function resetPassword(UserSecurityService $security, ClientSessionManager $sessions, AuthAuditor $auditor): void
    {
        $security->canManage(auth()->user(), $this->user);
        abort_unless(auth()->user()->can('resetPassword', $this->user), 403);

        $this->user->forceFill([
            'password' => Hash::make($this->temporaryPassword),
            'must_change_password' => $this->mustChangePassword,
        ])->save();

        // Una contraseña restablecida invalida las sesiones abiertas en Mobile y
        // Desktop: si el motivo fue una sospecha, dejarlas vivas anularía la
        // medida. Los tokens de API no se tocan — no representan a la persona y
        // su ciclo de vida lo gobierna la administración de tokens.
        if ((bool) config('client_sessions.revoke_on_password_change', true)) {
            $actor = auth()->user();
            $revocadas = $sessions->revokeAllFor(
                $this->user,
                null,
                $actor instanceof User ? $actor : null,
                'password_changed',
            );

            $auditor->record(AuthAuditor::PASSWORD_CHANGED, $actor instanceof User ? $actor : null, request(), null, [
                'target_user_id' => $this->user->getKey(),
                'sessions_revoked' => $revocadas,
            ]);
        }

        $this->dispatch('toast', type: 'success', message: 'Contraseña restablecida.');
    }

    public function render()
    {
        return view('livewire.admin.users.password-reset');
    }
}
