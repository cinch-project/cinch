<?php

namespace Cinch\Component\Schema\Type;

use Cinch\Component\Schema\ColumnDefinition;
use Cinch\Component\Schema\Type;
use InvalidArgumentException;

class Integer implements Type
{
    private const TYPE_RANGES = [
        'tinyint' => [-128, 127],
        'smallint' => [-32768, 32767],
        'mediumint' => [-8388608, 8388607],
        'int' => [-2147483648, 2147483647],
        'bigint' => [PHP_INT_MIN, PHP_INT_MAX]
    ];

    public function __construct(private readonly string $type, private readonly bool $isUnsigned)
    {
        if (!in_array($this->type, array_keys(self::TYPE_RANGES)))
            throw new InvalidArgumentException("invalid integer type, found '$this->type'");
    }

    public function compile(ColumnDefinition $definition, string $platformName): string
    {
        /* unsigned built into mysql, which we do not port the other platforms */
        if ($platformName == 'mysql' || $platformName == 'mariadb')
            return $this->type . ($this->isUnsigned ? ' unsigned' : '');

        if ($platformName == 'sqlite')
            return $this->addRangeCheck($definition, 'integer');

        /* pgsql has no tinyint, use smallint */
        if ($platformName == 'pgsql' && $this->type == 'tinyint')
            return $this->addRangeCheck($definition, 'smallint');

        /* only mysql supports this */
        if ($this->type == 'mediumint')
            return $this->addRangeCheck($definition, 'int');

        return $this->type; // tinyint, smallint, int, bigint
    }

    /* port int types across platforms using check constraints */
    private function addRangeCheck(ColumnDefinition $definition, string $returnType): string
    {
        $definition->checkBetween(...self::TYPE_RANGES[$this->type]);
        return $returnType;
    }
}
