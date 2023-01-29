<?php

namespace Cinch\Component\Schema;

abstract class Check
{
    public function __construct(protected readonly string $name, protected readonly string $columnName)
    {
    }

    abstract protected function getConstraintName(Session $session): string;

    abstract protected function getExpression(Session $session);

    public function compile(Session $session): string
    {
        $name = $this->name ?: $this->getConstraintName($session);
        if ($name)
            $name = 'constraint ' . $session->quoteIdentifier($name) . ' ';
        $expr = $this->getExpression($session);
        return "{$name}check ($expr)";
    }
}
