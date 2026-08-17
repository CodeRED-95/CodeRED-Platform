<?php

namespace App\Actions\ApiTokenRequests;

use App\Exceptions\OtpMaxResendsExceededException;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\OtpService;

class ResendOtpTokenAction
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    /**
     * Reenenvía un código OTP
     *
     * @return array{
     *     success: bool,
     *     message: string,
     *     resends_remaining: int,
     * }
     *
     * @throws OtpMaxResendsExceededException
     */
    public function execute(
        ApiTokenRequest $request,
        string $ip,
        ?string $userAgent = null,
    ): array {
        // Intentar reenviar
        $otp = $this->otpService->resend($request, $ip, $userAgent);

        if (! $otp) {
            throw new OtpMaxResendsExceededException;
        }

        $email = $request->requester_email;

        if (! is_string($email) || trim($email) === '') {
            throw new \RuntimeException('Esta solicitud no tiene un correo de entrega al que reenviar el codigo.');
        }

        \Illuminate\Support\Facades\Mail::to($email)->send(
            new \App\Mail\OtpCodeMail($request, $otp, self::maskEmail($email))
        );

        return [
            'success' => true,
            'message' => 'Código OTP reenviado a tu correo electrónico.',
            'resends_remaining' => $otp->getRemainingResends(),
        ];
    }

    /** Enmascara un correo: u***@example.com */
    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return '***@***';
        }

        [$local, $domain] = $parts;

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
