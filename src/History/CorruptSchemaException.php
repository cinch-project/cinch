<?php

namespace Cinch\History;

use RuntimeException;
use Throwable;

class CorruptSchemaException extends RuntimeException
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct("corrupt history schema: $message", $code, $previous);
    }
}
