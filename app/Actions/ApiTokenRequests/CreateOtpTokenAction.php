<?php

namespace App\Actions\ApiTokenRequests;

use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\OtpService;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class CreateOtpTokenAction
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    /**
     * Genera un OTP y lo envía por email
     *
     * @return array{
     *     email_masked: string,
     *     expires_in_minutes: int,
     *     resends_remaining: int,
     *     message: string
     * }
     */
    public function execute(
        ApiTokenRequest $request,
        string $ip,
        ?string $userAgent = null,
    ): array {
        // Verificar que la solicitud tiene estado apropiado
        if (!in_array($request->statusValue(), ['pending', 'approved'])) {
            throw new \InvalidArgumentException(
                'No se puede solicitar OTP para una solicitud en estado: ' . $request->statusValue()
            );
        }

        // Generar OTP
        $otp = $this->otpService->generate($request, $ip, $userAgent);

        // Obtener email descifrado para envío
        $email = $request->requester_email;

        // TODO: Enviar email con OTP (implementar en versión completa)
        // Esto requiere una Mailable que será creada en Fase 2
        // Mail::to($email)->queue(new OtpCodeMail($request, $otp, $this->maskEmail($email)));

        // Por ahora, loguear que se generó
        \Log::info('OTP generated for token request', [
            'request_id' => $request->id,
            'email_masked' => self::maskEmail($email),
            'ip' => $ip,
        ]);

        return [
            'email_masked' => self::maskEmail($email),
            'expires_in_minutes' => config('token-requests.otp.expires_in_minutes', 10),
            'resends_remaining' => $otp->getRemainingResends(),
            'message' => 'Código OTP enviado. Revisa tu correo electrónico.',
        ];
    }

    /**
     * Enmascara un email mostrando solo la primera letra y dominio
     * Ejemplo: user@example.com → u***@example.com
     */
    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        [$local, $domain] = $parts;
        $first = mb_substr($local, 0, 1);

        return $first . '***@' . $domain;
    }
}
