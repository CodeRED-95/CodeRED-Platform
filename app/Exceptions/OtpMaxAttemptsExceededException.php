<?php

namespace App\Exceptions;

class OtpMaxAttemptsExceededException extends OtpException
{
    public function __construct()
    {
        parent::__construct('Has excedido el máximo número de intentos. Solicita un nuevo código OTP.');
    }
}
