<?php

namespace App\Actions\ApiTokenRequests;

use App\Exceptions\OtpExpiredException;
use App\Exceptions\OtpMaxAttemptsExceededException;
use App\Exceptions\OtpMaxResendsExceededException;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\OtpService;

class VerifyOtpTokenAction
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    /**
     * Verifica un código OTP
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     remaining_attempts?: int,
     * }
     *
     * @throws OtpExpiredException
     * @throws OtpMaxAttemptsExceededException
     * @throws OtpMaxResendsExceededException
     */
    public function execute(
        ApiTokenRequest $request,
        string $code,
        string $ip,
        ?string $userAgent = null,
    ): array {
        // Obtener OTP actual
        $otp = $request->getLatestOtpValidation();

        // Verificar si existe OTP
        if (!$otp) {
            return [
                'success' => false,
                'message' => 'No hay código OTP activo. Solicita uno nuevo.',
            ];
        }

        // Verificar si ya fue validado
        if ($otp->isValidated()) {
            return [
                'success' => false,
                'message' => 'Este código OTP ya fue validado.',
            ];
        }

        // Verificar si expiró
        if ($otp->isExpired()) {
            throw new OtpExpiredException();
        }

        // Verificar si alcanzó máximo de intentos
        if ($otp->hasMaxAttemptsReached()) {
            throw new OtpMaxAttemptsExceededException();
        }

        // Verificar si alcanzó máximo de reenvíos
        if ($otp->hasMaxResendsReached()) {
            throw new OtpMaxResendsExceededException();
        }

        // Intentar verificar el código
        $isValid = $this->otpService->verify($request, $code, $ip, $userAgent);

        if (!$isValid) {
            return [
                'success' => false,
                'message' => 'Código OTP inválido.',
                'remaining_attempts' => $otp->getRemainingAttempts(),
            ];
        }

        return [
            'success' => true,
            'message' => 'Código OTP verificado correctamente.',
        ];
    }
}
