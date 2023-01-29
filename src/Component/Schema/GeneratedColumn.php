<?php

namespace Cinch\Component\Schema;

use Cinch\Component\Assert\Assert;

class GeneratedColumn
{
    public function __construct(private readonly string $expression, private readonly bool $isVirtual)
    {
        Assert::notEmpty($this->expression, 'generated column expression');
    }

    public function compile(string $platformName): string
    {
        /* <column_name> AS <expression> [PERSISTED] */
        if ($platformName == 'sqlsrv') {
            $storage = $this->isVirtual ? '' : ' persisted';
            return sprintf('as %s%s', $this->expression, $storage);
        }

        /* <column_name> GENERATED ALWAYS AS (<expression>) {VIRTUAL | STORED} */
        $storage = $this->isVirtual && $platformName != 'pgsql' ? 'virtual' : 'stored';
        return sprintf('generated always as (%s) %s', $this->expression, $storage);
    }
}
