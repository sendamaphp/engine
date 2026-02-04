<?php

namespace Sendama\Engine\Exceptions;

use RuntimeException;

final class DebuggingException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("DebuggingException: " . $message, $code, $previous);
    }
}
