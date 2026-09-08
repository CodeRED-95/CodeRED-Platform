<?php

namespace App\Exceptions;

use RuntimeException;

class EmailVerificationAttemptsExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Se alcanzó el máximo de intentos.');
    }
}
