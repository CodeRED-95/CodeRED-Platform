<?php

namespace App\Livewire\Account;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = $this->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function updateProfile(): void
    {
        $user = $this->user();
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $emailChanged = $email !== $user->email;

        $user->fill([
            'name' => trim(preg_replace('/\s+/u', ' ', $validated['name'])),
            'email' => $email,
        ]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->dispatch('toast', type: 'success', message: 'Tu perfil fue actualizado correctamente.');
    }

    public function updatePassword(): void
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

        $this->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();
        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->dispatch('toast', type: 'success', message: 'Tu contraseña fue actualizada correctamente.');
    }

    public function render(): View
    {
        return view('livewire.account.profile')->layout('layouts.app', ['pageTitle' => 'Mi perfil']);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
