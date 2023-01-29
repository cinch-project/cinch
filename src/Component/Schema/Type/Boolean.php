<?php

namespace Cinch\Component\Schema\Type;

use Cinch\Component\Schema\ColumnDefinition;
use Cinch\Component\Schema\Type;

class Boolean implements Type
{
    public function __construct(private readonly bool $forceInt)
    {
    }

    /**
     * @return bool
     */
    public function shouldForceInt(): bool
    {
        return $this->forceInt;
    }

    public function compile(ColumnDefinition $definition, string $platformName): string
    {
        if ($platformName == 'mysql' || $platformName == 'mariadb')
            return 'bit(1)';

        if ($platformName == 'sqlsrv')
            return 'bit';

        /* sqlite or pgsql with forceInt */
        if ($platformName == 'sqlite' || $this->forceInt)
            $definition->checkIn([0, 1], "{$definition->getName()} must be 0 or 1");

        if ($platformName == 'sqlite')
            return 'integer';

        // pgsql
        return $this->forceInt ? 'smallint' : 'bool';
    }
}
