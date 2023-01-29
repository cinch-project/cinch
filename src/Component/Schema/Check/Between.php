<?php

namespace Cinch\Component\Schema\Check;

use Cinch\Component\Schema\Check;
use Cinch\Component\Schema\Session;
use Exception;

class Between extends Check
{
    public function __construct(
        string $name,
        string $columnName,
        private readonly string|int|float $min,
        private readonly string|int|float $max,
        private readonly bool $not)
    {
        parent::__construct($name, $columnName);
    }

    /**
     * @throws Exception
     */
    protected function getConstraintName(Session $session): string
    {
        return sprintf('%s %s range: %s to %s',
            $this->columnName,
            $this->not ? 'within excluded' : 'out of',
            is_string($this->min) ? $session->quoteString($this->min) : $this->min,
            is_string($this->max) ? $session->quoteString($this->max) : $this->max
        );
    }

    /**
     * @throws Exception
     */
    protected function getExpression(Session $session): string
    {
        return sprintf('%s %s %s and %s',
            $session->quoteIdentifier($this->columnName),
            $this->not ? 'not between' : 'between',
            is_string($this->min) ? $session->quoteString($this->min) : $this->min,
            is_string($this->max) ? $session->quoteString($this->max) : $this->max
        );
    }
}
