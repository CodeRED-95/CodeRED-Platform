<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = true;

    public function authenticate(): void
    {
        $validated = $this->validate(
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ],
            [],
            [
                'email' => 'correo electrónico',
                'password' => 'contraseña',
            ]
        );

        if (! Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        request()->session()->regenerate();

        if ($user->must_change_password) {
            $this->redirect(route('account.change-password'), navigate: true);

            return;
        }

        $this->redirectIntended(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.guest', ['pageTitle' => 'Iniciar sesión']);
    }
}
