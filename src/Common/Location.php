<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;

class Location extends SingleValue
{
    const MAX_LENGTH = 760;

    private readonly bool $isSql;

    public function __construct(string $location)
    {
        parent::__construct(Assert::betweenLength($location, 1, self::MAX_LENGTH, message: 'location'));
        $this->isSql = strtolower(pathinfo($this->value, PATHINFO_EXTENSION)) == 'sql';
    }

    public function isSql(): bool
    {
        return $this->isSql;
    }

    public function equals(SingleValue $value): bool
    {
        return get_class($this) == get_class($value) &&
            strcmp(mb_strtolower($this->value, 'UTF-8'), mb_strtolower($value->value, 'UTF-8')) == 0;
    }
}