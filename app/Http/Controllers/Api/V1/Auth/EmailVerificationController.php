<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\EmailVerificationAttemptsExceededException;
use App\Exceptions\EmailVerificationCooldownException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function send(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $user = $this->findUser((string) $validated['email']);

        if ($user instanceof User && $user->requiresEmailVerification()) {
            try {
                $verification->ensureCode($user, $request);
            } catch (EmailVerificationCooldownException) {
                // Respuesta pública uniforme para evitar enumeración.
            }

            // La respuesta pública es deliberadamente indistinguible para no
            // convertir esta ruta en un enumerador de cuentas.
            return $this->status(['expires_in' => 0, 'resend_available_in' => 0]);
        }

        return $this->status(['expires_in' => 0, 'resend_available_in' => 0]);
    }

    public function verify(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
        ]);
        $user = $this->findUser((string) $validated['email']);

        if (! $user instanceof User || ! $user->requiresEmailVerification()) {
            return response()->json(['success' => false, 'message' => 'Código incorrecto o expirado.'], 422);
        }

        try {
            $result = $verification->verify($user, (string) $validated['code'], $request);
        } catch (EmailVerificationAttemptsExceededException) {
            return response()->json(['success' => false, 'message' => 'Se alcanzó el máximo de intentos.'], 429);
        }

        if ($result['status'] !== 'verified') {
            return response()->json(['success' => false, 'message' => $result['status'] === 'expired' ? 'Código expirado.' : 'Código incorrecto.'], 422);
        }

        return response()->json(['success' => true, 'data' => ['email_verified' => true]]);
    }

    public function resend(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $user = $this->findUser((string) $validated['email']);

        if (! $user instanceof User || ! $user->requiresEmailVerification()) {
            return $this->status(['expires_in' => 0, 'resend_available_in' => 0]);
        }

        try {
            $verification->ensureCode($user, $request, true);

            return $this->status(['expires_in' => 0, 'resend_available_in' => 0]);
        } catch (EmailVerificationCooldownException) {
            // El cooldown de dominio no revela si el correo existe.
        }

        return $this->status(['expires_in' => 0, 'resend_available_in' => 0]);
    }

    private function findUser(string $email): ?User
    {
        return User::query()->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
    }

    private function status(array $status): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [
            'verification_required' => $status['expires_in'] > 0,
            'expires_in' => $status['expires_in'],
            'resend_available_in' => $status['resend_available_in'],
        ]]);
    }
}
