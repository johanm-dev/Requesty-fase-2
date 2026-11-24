<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AuthException extends RuntimeException
{
    public function __construct(string $message = 'No autenticado.', int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
