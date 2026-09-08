<?php

namespace App\Exceptions;

use RuntimeException;

class EmailVerificationCooldownException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Debes esperar antes de solicitar otro código.');
    }
}
