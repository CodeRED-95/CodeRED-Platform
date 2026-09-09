<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'pageTitle' => 'Crear cuenta',
        ]);
    }

    public function store(
        RegisterRequest $request,
        UserRegistrationService $registration,
        EmailVerificationService $verification,
    ): RedirectResponse {
        $user = $registration->create($request->validated());

        Auth::login($user);
        $request->session()->regenerate();
        $verification->ensureCode($user, $request, true);

        return redirect()->route('email.verify')->with('success', 'Cuenta creada. Revisa tu correo para verificarla.');
    }
}
