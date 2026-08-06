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

        if (!$otp) {
            throw new OtpMaxResendsExceededException();
        }

        // TODO: Enviar email con OTP (implementar Mail::queue)
        // Mail::to($request->requester_email)->queue(new OtpCodeMail(...));

        return [
            'success' => true,
            'message' => 'Código OTP reenviado a tu correo electrónico.',
            'resends_remaining' => $otp->getRemainingResends(),
        ];
    }
}
