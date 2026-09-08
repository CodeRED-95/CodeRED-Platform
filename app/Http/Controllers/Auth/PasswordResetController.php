<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password', ['pageTitle' => 'Recuperar contraseña']);
    }

    public function send(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->validated('email')));
        Password::sendResetLink(['email' => $email]);

        return back()->with('status', 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.');
    }

    public function resetForm(string $token, string $email): View
    {
        return view('auth.reset-password', compact('token', 'email'))->with('pageTitle', 'Restablecer contraseña');
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $status = Password::reset(
            [
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Contraseña actualizada. Ya puedes iniciar sesión.');
        }

        return back()->withErrors(['email' => 'El enlace no es válido o ya expiró.']);
    }
}
