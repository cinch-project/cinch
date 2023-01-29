<?php

namespace Cinch\Component\Schema;

interface Type
{
    public function compile(ColumnDefinition $definition, string $platformName): string;
}
