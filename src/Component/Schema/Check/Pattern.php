<?php

namespace Cinch\Component\Schema\Check;

use Cinch\Component\Schema\Check;
use Cinch\Component\Schema\Session;
use Exception;
use RuntimeException;

class Pattern extends Check
{
    public function __construct(
        string $name,
        string $columnName,
        private readonly string $pattern,
        private readonly bool $isCaseSensitive,
        private readonly bool $not)
    {
        parent::__construct($name, $columnName);
    }

    protected function getConstraintName(Session $session): string
    {
        return sprintf('%s %s pattern', $this->columnName, $this->not ? 'must not match' : 'must match');
    }

    /**
     * @throws Exception
     */
    protected function getExpression(Session $session): string
    {
        $column = $session->quoteIdentifier($this->columnName);
        $pattern = $session->quoteString(match ($name = $session->getPlatformName()) {
            'mysql', 'mariadb', 'pgsql' => $this->pattern,
            default => throw new RuntimeException("'$name' does not support regular expressions")
        });

        if ($name == 'pgsql') {
            $operator = $this->not ? '!~' : '~';
            if ($this->isCaseSensitive)
                $operator .= '*';
            return sprintf('%s %s %s', $column, $operator, $pattern);
        }

        $not = $this->not ? 'not ' : '';
        $options = $this->isCaseSensitive ? "'c'" : "'i'";
        return "{$not}regexp_like($column, $pattern, $options)";
    }
}
