<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Services\Auth\EmailVerificationService;
use App\Services\Auth\UserRegistrationService;
use Illuminate\Http\JsonResponse;

final class RegisteredUserController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        UserRegistrationService $registration,
        EmailVerificationService $verification,
    ): JsonResponse {
        $user = $registration->create($request->validated());
        $status = $verification->ensureCode($user, $request, true);

        return response()->json([
            'success' => true,
            'verification_required' => true,
            'message' => 'Cuenta creada. Revisa tu correo para verificarla.',
            'data' => [
                'expires_in' => $status['expires_in'],
                'resend_available_in' => $status['resend_available_in'],
            ],
        ], 201);
    }
}
