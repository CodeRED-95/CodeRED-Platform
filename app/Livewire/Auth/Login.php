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

        request()->session()->regenerate();

        $this->redirectIntended(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.guest', ['pageTitle' => 'Iniciar sesión']);
    }
}
