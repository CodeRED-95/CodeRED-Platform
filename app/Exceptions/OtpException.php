<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Response;

class OtpException extends Exception
{
    public function __construct(string $message = '', int $code = Response::HTTP_UNPROCESSABLE_ENTITY)
    {
        parent::__construct($message, $code);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->message,
            'error' => 'otp_error',
        ], $this->code);
    }
}
