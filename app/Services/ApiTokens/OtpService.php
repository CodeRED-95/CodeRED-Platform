<?php

namespace App\Services\ApiTokens;

use App\Enums\TokenRequestAuditAction;
use App\Models\ApiTokenRequest;
use App\Models\OtpValidation;
use App\Models\TokenRequestAuditLog;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    private int $expiresInMinutes = 10;

    private int $maxAttempts = 5;

    private int $maxResends = 3;

    public function __construct()
    {
        $this->expiresInMinutes = config('token-requests.otp_expires_in_minutes', 10);
        $this->maxAttempts = config('token-requests.otp_max_attempts', 5);
        $this->maxResends = config('token-requests.otp_max_resends', 3);
    }

    /**
     * Genera un código OTP de 6 dígitos y lo almacena
     */
    public function generate(ApiTokenRequest $request, string $ip, ?string $userAgent = null): OtpValidation
    {
        // Invalidar OTP anteriores
        $request->otpValidations()->delete();

        // Generar código de 6 dígitos
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // El codigo en claro solo existe en memoria durante esta llamada: en la
        // base queda unicamente su hash. Se devuelve junto a la validacion
        // porque quien envia el correo lo necesita, y no hay otra forma de
        // recuperarlo despues.
        $validation = OtpValidation::create([
            'api_token_request_id' => $request->id,
            'email_blind_index' => $request->requester_email_blind_index,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($this->expiresInMinutes),
            'max_attempts' => $this->maxAttempts,
            'max_resends' => $this->maxResends,
            'requested_ip' => $ip,
            'requested_user_agent' => $userAgent,
        ]);

        // Registrar en auditoría
        TokenRequestAuditLog::logAction(
            $request,
            TokenRequestAuditAction::OtpRequested,
            ip: $ip,
            userAgent: $userAgent,
            details: [
                'expires_in_minutes' => $this->expiresInMinutes,
                'max_attempts' => $this->maxAttempts,
                'max_resends' => $this->maxResends,
            ]
        );

        $validation->setPlainCode($code);

        return $validation;
    }

    /**
     * Verifica un código OTP
     *
     * @throws \Exception
     */
    public function verify(ApiTokenRequest $request, string $code, string $ip, ?string $userAgent = null): bool
    {
        $otp = $request->getLatestOtpValidation();

        if (! $otp) {
            TokenRequestAuditLog::logAction(
                $request,
                TokenRequestAuditAction::OtpVerified,
                ip: $ip,
                userAgent: $userAgent,
                details: ['result' => 'no_otp_found']
            );

            return false;
        }

        // Verificar si ya fue validado
        if ($otp->isValidated()) {
            TokenRequestAuditLog::logAction(
                $request,
                TokenRequestAuditAction::TokenAlreadyRevealed,
                ip: $ip,
                userAgent: $userAgent,
            );

            return false;
        }

        // Verificar si expiró
        if ($otp->isExpired()) {
            $otp->update(['last_attempt_at' => now(), 'last_attempt_ip' => $ip]);

            TokenRequestAuditLog::logAction(
                $request,
                TokenRequestAuditAction::OtpExpired,
                ip: $ip,
                userAgent: $userAgent,
            );

            return false;
        }

        // Verificar si alcanzó el máximo de intentos
        if ($otp->hasMaxAttemptsReached()) {
            TokenRequestAuditLog::logAction(
                $request,
                TokenRequestAuditAction::OtpMaxAttemptsReached,
                ip: $ip,
                userAgent: $userAgent,
            );

            return false;
        }

        // Registrar intento
        $otp->recordAttempt($ip, $userAgent);

        // Verificar código
        if (! $otp->verifyCode($code)) {
            TokenRequestAuditLog::logAction(
                $request,
                TokenRequestAuditAction::OtpVerified,
                ip: $ip,
                userAgent: $userAgent,
                details: ['result' => 'invalid_code', 'remaining_attempts' => $otp->getRemainingAttempts()]
            );

            return false;
        }

        // Marcar como validado
        $otp->markAsValidated();

        // Actualizar request
        $request->update(['otp_validated_at' => now()]);

        // Registrar éxito en auditoría
        TokenRequestAuditLog::logAction(
            $request,
            TokenRequestAuditAction::OtpVerified,
            ip: $ip,
            userAgent: $userAgent,
            details: ['result' => 'success']
        );

        return true;
    }

    /**
     * Reenenvía un código OTP
     *
     * @throws \Exception
     */
    public function resend(ApiTokenRequest $request, string $ip, ?string $userAgent = null): ?OtpValidation
    {
        $otp = $request->getLatestOtpValidation();

        if (! $otp) {
            return $this->generate($request, $ip, $userAgent);
        }

        // Verificar si alcanzó el máximo de reenvíos
        if ($otp->hasMaxResendsReached()) {
            TokenRequestAuditLog::logAction(
                $request,
                TokenRequestAuditAction::OtpMaxResendsReached,
                ip: $ip,
                userAgent: $userAgent,
            );

            return null;
        }

        // Verificar si ya fue validado
        if ($otp->isValidated()) {
            TokenRequestAuditLog::logAction(
                $request,
                TokenRequestAuditAction::TokenAlreadyRevealed,
                ip: $ip,
                userAgent: $userAgent,
            );

            return null;
        }

        // Registrar reenvío
        $otp->recordResend($ip, $userAgent);

        // Registrar en auditoría
        TokenRequestAuditLog::logAction(
            $request,
            TokenRequestAuditAction::OtpResent,
            ip: $ip,
            userAgent: $userAgent,
            details: [
                'resends_remaining' => $otp->getRemainingResends(),
                'expires_at' => $otp->expires_at,
            ]
        );

        return $otp;
    }

    /**
     * Verifica si una solicitud tiene OTP validado
     */
    public function isValidated(ApiTokenRequest $request): bool
    {
        return $request->hasOtpBeenValidated();
    }

    /**
     * Obtiene los detalles del OTP para mostrar en la UI
     */
    public function getStatus(ApiTokenRequest $request): array
    {
        $otp = $request->getLatestOtpValidation();

        if (! $otp) {
            return [
                'status' => 'not_sent',
                'message' => 'Código OTP no enviado',
            ];
        }

        if ($otp->isValidated()) {
            return [
                'status' => 'validated',
                'message' => 'Código OTP validado',
            ];
        }

        if ($otp->isExpired()) {
            return [
                'status' => 'expired',
                'message' => 'Código OTP expirado',
                'remaining_resends' => $otp->getRemainingResends(),
                'can_resend' => $otp->canResend(),
            ];
        }

        if ($otp->hasMaxAttemptsReached()) {
            return [
                'status' => 'max_attempts',
                'message' => 'Máximo de intentos alcanzado',
                'remaining_resends' => $otp->getRemainingResends(),
                'can_resend' => $otp->canResend(),
            ];
        }

        if ($otp->hasMaxResendsReached()) {
            return [
                'status' => 'max_resends',
                'message' => 'Máximo de reenvíos alcanzado',
            ];
        }

        return [
            'status' => 'pending',
            'message' => 'Código OTP pendiente de validación',
            'remaining_minutes' => $otp->getRemainingMinutes(),
            'remaining_attempts' => $otp->getRemainingAttempts(),
            'remaining_resends' => $otp->getRemainingResends(),
            'can_verify' => $otp->canVerify(),
            'can_resend' => $otp->canResend(),
            'expires_at' => $otp->expires_at,
        ];
    }
}
