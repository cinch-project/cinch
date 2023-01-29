<?php

namespace Cinch\Component\Schema;

use Exception;
use InvalidArgumentException;

class DefaultValue
{
    public function __construct(private readonly string|int|float|bool|null $value, private readonly bool $isExpression)
    {
        if ($this->isExpression && !is_string($this->value))
            throw new InvalidArgumentException(
                "expression default value must be a string, found " . get_debug_type($this->value));
    }

    /**
     * @throws Exception
     */
    public function compile(Session $session, bool $forceIntBool): string
    {
        if ($this->isExpression) {
            $value = $this->value; // verbatim: current_timestamp, nextval('seq'), etc.
        }
        else if (is_string($this->value)) {
            $value = $session->quoteString($this->value);
        }
        else if (is_bool($this->value)) {
            if (!$forceIntBool && $session->getPlatformName() == 'pgsql')
                $value = $this->value ? "'t'" : "'f'";
            else
                $value = $this->value ? 1 : 0;
        }
        else if (is_null($this->value)) {
            $value = 'null';
        }
        else {
            $value = $this->value; // int|float
        }

        return "default $value";
    }
}
