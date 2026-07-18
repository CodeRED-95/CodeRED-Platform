<?php

namespace App\Livewire\Admin\Users;

use App\Models\User;
use App\Modules\Users\Services\UserSecurityService;
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

    public function resetPassword(UserSecurityService $security): void
    {
        $security->canManage(auth()->user(), $this->user);
        abort_unless(auth()->user()->can('resetPassword', $this->user), 403);

        $this->user->forceFill([
            'password' => Hash::make($this->temporaryPassword),
            'must_change_password' => $this->mustChangePassword,
        ])->save();

        $this->dispatch('toast', type: 'success', message: 'Contraseña restablecida.');
    }

    public function render()
    {
        return view('livewire.admin.users.password-reset');
    }
}
