<?php

namespace Cinch\Component\Schema\Check;

use Cinch\Component\Schema\Check;
use Cinch\Component\Schema\Session;
use Exception;

class BetweenLength extends Check
{
    public function __construct(
        string $name,
        string $columnName,
        private readonly int $min,
        private readonly int $max,
        private readonly bool $not)
    {
        parent::__construct($name, $columnName);
    }

    /**
     * @throws Exception
     */
    protected function getConstraintName(Session $session): string
    {
        return sprintf('%s %s length range: %d to %d',
            $this->columnName,
            $this->not ? 'within excluded' : 'out of',
            $this->min,
            $this->max
        );
    }

    /**
     * @throws Exception
     */
    protected function getExpression(Session $session): string
    {
        return sprintf('%s(%s) %s %d and %d',
            $session->getPlatformName() == 'sqlsrv' ? 'len' : 'length',
            $session->quoteIdentifier($this->columnName),
            $this->not ? 'not between' : 'between',
            $this->min,
            $this->max
        );
    }
}
