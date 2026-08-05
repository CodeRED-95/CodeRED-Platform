<?php

namespace App\Exceptions;

class OtpMaxResendsExceededException extends OtpException
{
    public function __construct()
    {
        parent::__construct('Has excedido el máximo número de reenvíos. Contacta con soporte.');
    }
}
