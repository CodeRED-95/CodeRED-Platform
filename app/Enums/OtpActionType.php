<?php

namespace App\Enums;

enum OtpActionType: string
{
    case Send = 'send';
    case Verify = 'verify';
    case Resend = 'resend';
    case Expire = 'expire';
    case MaxAttemptsReached = 'max_attempts_reached';
    case MaxResendsReached = 'max_resends_reached';
    case Validate = 'validate';

    public function label(): string
    {
        return match ($this) {
            self::Send => 'Código OTP enviado',
            self::Verify => 'Código OTP verificado',
            self::Resend => 'Código OTP reenviado',
            self::Expire => 'Código OTP expirado',
            self::MaxAttemptsReached => 'Máximo de intentos alcanzado',
            self::MaxResendsReached => 'Máximo de reenvíos alcanzado',
            self::Validate => 'Código OTP validado',
        };
    }
}
