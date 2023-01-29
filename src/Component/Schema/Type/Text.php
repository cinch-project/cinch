<?php

namespace Cinch\Component\Schema\Type;

use Cinch\Component\Schema\ColumnDefinition;
use Cinch\Component\Schema\Type;

class Text implements Type
{
    public function __construct(private readonly string $type)
    {
    }

    public function compile(ColumnDefinition $definition, string $platformName): string
    {
        if ($platformName == 'mysql' || $platformName == 'mariadb')
            return $this->type;

        $varchar = $platformName == 'sqlsrv' ? 'nvarchar' : 'varchar';

        if ($this->type == 'tinytext') {
            if ($platformName == 'sqlite') {
                $column = $definition->getName();
                $definition->checkBetween(0, 255, "$column value too long for $this->type(255)");
                return 'text';
            }

            return "$varchar(255)";
        }

        /* sqlsrv, pgsql, sqlite */
        return $platformName == 'sqlsrv' ? "$varchar(max)" : 'text';
    }
}
