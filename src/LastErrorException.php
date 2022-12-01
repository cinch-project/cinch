<?php

namespace Cinch;

use Exception;
use Throwable;

class LastErrorException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        if ($message)
            $message .= ' - ';
        parent::__construct($message . error_get_last()['message'], $code, $previous);
    }
}