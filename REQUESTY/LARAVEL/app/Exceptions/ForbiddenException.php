<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ForbiddenException extends RuntimeException
{
    public function __construct(string $message = 'Acceso denegado para este rol o recurso.', int $code = 403, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
