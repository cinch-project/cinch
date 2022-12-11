<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Symfony\Component\Filesystem\Path;

class Location extends SingleValue
{
    const MAX_LENGTH = 512;

    private readonly bool $isSql;

    public function __construct(string $location)
    {
        if (Path::isAbsolute($location))
            throw new AssertException("location must be a relative path, found $location");

        $this->isSql = Assert::in(strtolower(pathinfo($location, PATHINFO_EXTENSION)),
                ['sql', 'php'], 'location extension .php or .sql') == 'sql';

        parent::__construct(Assert::betweenLength($location, 1, self::MAX_LENGTH, message: 'location'));
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