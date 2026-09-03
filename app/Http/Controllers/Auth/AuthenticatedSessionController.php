<?php

namespace App\Http\Controllers\Auth;

use App\Core\Auth\AuthenticatedHome;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\TrustedRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController
{
    public function create(Request $request): View
    {
        return view('auth.login', [
            'pageTitle' => 'Iniciar sesión',
            // Otras apps del ecosistero (Store, Mobile, Desktop) mandan aqui
            // a un usuario sin sesion con `?redirect=` para poder devolverlo
            // a donde estaba tras iniciar sesion. Se valida ya en el GET,
            // asi el formulario nunca reenvia un destino no confiable.
            'redirect' => TrustedRedirect::resolve($request->query('redirect')),
        ]);
    }

    public function store(LoginRequest $request, AuthenticatedHome $home): RedirectResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user?->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->regenerate();

        if ($user->must_change_password) {
            return redirect()->route('account.change-password');
        }

        // El destino externo (Store, Mobile, Desktop...) gana sobre el
        // "intended" interno de Platform y sobre el home por defecto, pero
        // solo si es un dominio del ecosistema: TrustedRedirect ya lo validó
        // al cargar el formulario, y se revalida aquí porque el campo llega
        // desde un input del navegador y no hay que confiar en él sin más.
        $redirect = TrustedRedirect::resolve($request->input('redirect'));

        if ($redirect !== null) {
            return redirect()->away($redirect);
        }

        return redirect()->intended($home->route($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
