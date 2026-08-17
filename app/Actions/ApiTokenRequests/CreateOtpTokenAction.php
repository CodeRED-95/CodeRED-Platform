<?php

declare(strict_types=1);

namespace App\Actions\ApiTokenRequests;

use App\Mail\OtpCodeMail;
use App\Models\ApiTokenRequest;
use App\Services\ApiTokens\OtpService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Genera el codigo de un solo uso que autoriza a revelar el token, y lo envia.
 *
 * Este camino existe solo para las solicitudes con entrega por correo. Cuando
 * alguien pide el token por Telegram o WhatsApp no hay direccion a la que
 * mandar el codigo: esas se entregan de forma asistida desde el panel, por el
 * mismo canal que eligio la persona, y la pantalla publica debe decirlo en vez
 * de ofrecer un boton que no lleva a ninguna parte.
 */
class CreateOtpTokenAction
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    /**
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
        if (! in_array($request->statusValue(), ['pending', 'approved'], true)) {
            throw new \InvalidArgumentException(
                'No se puede solicitar OTP para una solicitud en estado: '.$request->statusValue()
            );
        }

        $email = $request->requester_email;

        // Sin correo no hay codigo posible. Antes se llamaba a maskEmail(null) y
        // la pagina respondia con un error 500; ahora se explica que hacer.
        if (! is_string($email) || trim($email) === '') {
            throw new RuntimeException($this->mensajeSinCorreo($request));
        }

        $otp = $this->otpService->generate($request, $ip, $userAgent);

        // El envio estaba comentado con un TODO: se generaba el codigo, se
        // anunciaba "revisa tu correo" y no salia nada. Sin esto, la solicitud
        // no se puede completar por mucho que se apruebe.
        // send(), no queue(): el codigo en claro no se persiste, asi que un
        // trabajo en cola lo perderia al deserializar el modelo.
        Mail::to($email)->send(new OtpCodeMail($request, $otp, self::maskEmail($email)));

        Log::info('otp_generated_for_token_request', [
            'request_id' => $request->id,
            'email_masked' => self::maskEmail($email),
            'ip' => $ip,
        ]);

        return [
            'email_masked' => self::maskEmail($email),
            'expires_in_minutes' => (int) config('token-requests.otp.expires_in_minutes', 10),
            'resends_remaining' => $otp->getRemainingResends(),
            'message' => 'Codigo enviado a '.self::maskEmail($email).'. Revisa tu correo electronico.',
        ];
    }

    /** Explica, segun el canal elegido, por donde llegara el token. */
    private function mensajeSinCorreo(ApiTokenRequest $request): string
    {
        $canal = match (true) {
            filled($request->delivery_telegram_username) => 'Telegram',
            filled($request->delivery_whatsapp_number) => 'WhatsApp',
            default => null,
        };

        if ($canal === null) {
            return 'Esta solicitud no tiene un correo de entrega, asi que no es posible enviar el codigo de verificacion. Contacta con el administrador.';
        }

        return 'Esta solicitud se entrega por '.$canal.'. El token te llegara por ese medio cuando sea aprobada; no hace falta codigo de verificacion aqui.';
    }

    /**
     * Enmascara un correo: u***@example.com
     */
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
