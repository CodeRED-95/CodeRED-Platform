<?php

namespace App\Exceptions;

class OtpExpiredException extends OtpException
{
    public function __construct()
    {
        parent::__construct('El código OTP ha expirado. Solicita uno nuevo.');
    }
}
