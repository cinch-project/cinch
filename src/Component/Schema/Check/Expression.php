<?php

namespace Cinch\Component\Schema\Check;

use Cinch\Component\Schema\Check;
use Cinch\Component\Schema\Session;

class Expression extends Check
{
    public function __construct(string $name, private readonly string $expression)
    {
        parent::__construct($name, '');
    }

    protected function getExpression(Session $session): string
    {
        return $this->expression;
    }

    protected function getConstraintName(Session $session): string
    {
        return '';
    }
}
