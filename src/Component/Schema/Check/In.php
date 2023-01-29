<?php

namespace Cinch\Component\Schema\Check;

use Cinch\Component\Schema\Check;
use Cinch\Component\Schema\Session;
use Exception;
use InvalidArgumentException;

class In extends Check
{
    private readonly array $values;

    public function __construct(
        string $name,
        string $columnName,
        array $values,
        private readonly bool $not)
    {
        $vals = [];

        foreach ($values as $i => $v) {
            if (is_string($v))
                $vals[] = $session->quoteString($v);
            else if (is_int($v) || is_float($v))
                $vals[] = $v;
            else
                throw new InvalidArgumentException(
                    "in value[$i] must be a string|int|float, found " . get_debug_type($v));
        }

        $this->values = $vals;
        parent::__construct($name, $columnName);
    }

    /**
     * @throws Exception
     */
    protected function getExpression(Session $session): string
    {
        return sprintf('%s %s (%s)',
            $session->quoteIdentifier($this->columnName),
            $this->not ? 'not in' : 'in',
            implode(', ', $this->values)
        );
    }

    protected function getConstraintName(Session $session): string
    {
        return sprintf('%s %s a set of values',
            $this->columnName,
            $this->not ? 'must not be contained within' : 'must be contained within'
        );
    }
}
