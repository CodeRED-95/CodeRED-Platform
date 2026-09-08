<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Auth\AuthenticatedHome;
use App\Exceptions\EmailVerificationAttemptsExceededException;
use App\Exceptions\EmailVerificationCooldownException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailCodeRequest;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function show(Request $request, EmailVerificationService $verification): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        if (! $user->requiresEmailVerification()) {
            return redirect()->intended(app(AuthenticatedHome::class)->route($user));
        }

        return view('auth.verify-email', [
            'pageTitle' => 'Verifica tu correo',
            'emailMasked' => $verification::maskEmail((string) $user->email),
            'status' => $verification->status($user),
            'resendAvailableIn' => (int) session('resend_available_in', $verification->status($user)['resend_available_in']),
        ]);
    }

    public function verify(VerifyEmailCodeRequest $request, EmailVerificationService $verification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $result = $verification->verify($user, (string) $request->validated('code'), $request);
        } catch (EmailVerificationAttemptsExceededException) {
            return back()->withErrors(['code' => 'Has alcanzado el máximo de intentos. Solicita un nuevo código.']);
        }

        if ($result['status'] === 'verified') {
            return redirect()->intended(app(AuthenticatedHome::class)->route($user))
                ->with('success', 'Correo verificado correctamente.');
        }

        return back()->withErrors([
            'code' => $result['status'] === 'expired'
                ? 'El código expiró. Solicita uno nuevo.'
                : 'El código es incorrecto.',
        ]);
    }

    public function resend(Request $request, EmailVerificationService $verification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $status = $verification->ensureCode($user, $request, true);
        } catch (EmailVerificationCooldownException $exception) {
            return back()->withErrors(['code' => 'Espera '.$exception->retryAfter.' segundos antes de reenviar.']);
        }

        return back()->with('success', 'Código enviado nuevamente. Revisa tu correo.')
            ->with('resend_available_in', $status['resend_available_in']);
    }
}
