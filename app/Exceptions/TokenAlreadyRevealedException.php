<?php

namespace App\Exceptions;

class TokenAlreadyRevealedException extends OtpException
{
    public function __construct()
    {
        parent::__construct('El token ya ha sido revelado. No puede volver a mostrarse.');
    }
}
