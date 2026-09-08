<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EmailVerificationMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $code,
        public string $emailMasked,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('mail.from.name')),
            subject: 'Verifica tu correo electrónico - CodeRED',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.email-verification',
            text: 'emails.email-verification-text',
            with: [
                'code' => $this->code,
                'emailMasked' => $this->emailMasked,
                'expiresInMinutes' => 10,
            ],
        );
    }
}
