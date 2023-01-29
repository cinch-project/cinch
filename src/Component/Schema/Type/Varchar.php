<?php

namespace Cinch\Component\Schema\Type;

use Cinch\Component\Schema\ColumnDefinition;
use Cinch\Component\Schema\Type;

class Varchar implements Type
{
    public function __construct(private readonly int $length)
    {
    }

    public function compile(ColumnDefinition $definition, string $platformName): string
    {
        $varchar = $platformName == 'sqlsrv' ? 'nvarchar' : 'varchar';

        if ($platformName == 'sqlite') {
            $column = $definition->getName();
            $definition->checkBetween(0, $this->length, "$column value too long for $varchar($this->length)");
            return 'text';
        }

        return "$varchar($this->length)";
    }
}
