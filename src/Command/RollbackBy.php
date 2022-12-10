<?php

namespace Cinch\Command;

use Cinch\Common\Location;
use Cinch\Component\Assert\Assert;
use DateTimeInterface;

class RollbackBy
{
    const TAG = 'tag';
    const DATE = 'date';
    const COUNT = 'count';
    const SCRIPT = 'script';

    public static function tag(string $tag): self
    {
        return new self(self::TAG, Assert::betweenLength($tag, 1, 64, message: 'rollback-by-tag'));
    }

    public static function date(DateTimeInterface $date): self
    {
        return new self(self::COUNT, $date);
    }

    public static function count(int $count): self
    {
        return new self(self::COUNT, Assert::between($count, 1, 100, 'rollback-by-count'));
    }

    public static function scripts(array $locations): self
    {
        Assert::notEmpty($locations, 'rollback-by-script location(s)');
        foreach ($locations as $i => $s)
            Assert::class($s, Location::class, "rollback-by-script locations[$i]");
        return new self(self::SCRIPT, $locations);
    }


    private function __construct(
        public readonly string $type,
        public readonly int|string|DateTimeInterface|array $value)
    {
    }
}