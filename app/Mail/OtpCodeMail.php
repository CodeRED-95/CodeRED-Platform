<?php

namespace App\Mail;

use App\Models\ApiTokenRequest;
use App\Models\OtpValidation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ApiTokenRequest $request,
        public OtpValidation $otp,
        public string $emailMasked,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Código de verificación OTP - CodeRED Platform',
        );
    }

    public function content(): Content
    {
        // Generar código ficticio para demostración
        // En producción, esto sería el código real (pero hasheado en la BD)
        $demoCode = '123456'; // Esto es solo para el email

        return new Content(
            view: 'emails.otp-code',
            with: [
                'request' => $this->request,
                'emailMasked' => $this->emailMasked,
                'expiresInMinutes' => config('token-requests.otp.expires_in_minutes', 10),
                'expiresAt' => $this->otp->expires_at,
                'maxAttempts' => config('token-requests.otp.max_attempts', 5),
                'maxResends' => config('token-requests.otp.max_resends', 3),
                'trackingCode' => $this->request->tracking_code ?? 'N/A',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
