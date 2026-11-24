<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class DomainValidationException extends RuntimeException
{
    public function __construct(string $message, int $code = 422, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
