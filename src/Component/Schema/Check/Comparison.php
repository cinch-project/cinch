<?php

namespace Cinch\Component\Schema\Check;

use Cinch\Component\Schema\Check;
use Cinch\Component\Schema\Session;
use Exception;

class Comparison extends Check
{
    public function __construct(
        string $name,
        string $columnName,
        private readonly Operator $operator,
        private readonly string|int|float $value)
    {
        parent::__construct($name, $columnName);
    }

    /**
     * @throws Exception
     */
    protected function getConstraintName(Session $session): string
    {
        $operator = match ($this->operator) {
            Operator::EQ => 'must be equal to',
            Operator::NE => 'must not be equal to',
            Operator::GE => 'must be greater than or equal to',
            Operator::LE => 'must be less than or equal to',
            Operator::GT => 'must be greater than',
            Operator::LT => 'must be less than'
        };

        return sprintf('%s %s %s', $this->columnName, $operator, $this->value);
    }

    /**
     * @throws Exception
     */
    protected function getExpression(Session $session): string
    {
        $value = is_string($this->value) ? $session->quoteString($this->value) : $this->value;
        return sprintf('%s %s %s', $session->quoteIdentifier($this->columnName), $this->operator->value, $value);
    }
}
