<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;

class Location extends SingleValue
{
    const MAX_LENGTH = 760;

    public function __construct(string $location)
    {
        parent::__construct(Assert::betweenLength($location, 1, self::MAX_LENGTH, message: 'location'));
    }

    public function equals(SingleValue $value): bool
    {
        return get_class($this) == get_class($value) &&
            strcmp(mb_strtolower($this->value, 'UTF-8'), mb_strtolower($value->value, 'UTF-8')) == 0;
    }
}