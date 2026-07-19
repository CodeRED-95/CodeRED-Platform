<?php

namespace App\Livewire\Account;

use App\Core\Auth\AuthenticatedHome;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);
    }

    public function updatePassword(AuthenticatedHome $home): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers(), 'confirmed', 'different:current_password'],
            'password_confirmation' => ['required'],
        ], [], [
            'current_password' => 'contraseña actual',
            'password' => 'nueva contraseña',
            'password_confirmation' => 'confirmación de contraseña',
        ]);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        $this->reset(['current_password', 'password', 'password_confirmation']);

        $this->redirect($home->route($user), navigate: true);
    }

    public function render()
    {
        return view('livewire.account.change-password')->layout('layouts.app', ['pageTitle' => 'Cambiar contraseña']);
    }
}
