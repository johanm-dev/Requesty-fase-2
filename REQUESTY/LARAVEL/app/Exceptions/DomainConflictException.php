<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DomainConflictException extends RuntimeException
{
    public function __construct(string $message, int $code = 409, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
