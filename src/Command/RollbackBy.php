<?php

namespace Cinch\Command;

use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\History\DeploymentTag;
use DateTimeInterface;

class RollbackBy
{
    const TAG = 'tag';
    const DATE = 'date';
    const COUNT = 'count';
    const PATHS = 'paths';

    public static function tag(DeploymentTag $tag): self
    {
        return new self(self::TAG, $tag);
    }

    public static function date(DateTimeInterface $date): self
    {
        return new self(self::DATE, $date);
    }

    public static function count(int $count): self
    {
        return new self(self::COUNT, Assert::greaterThan($count, 0, 'rollback-by-count'));
    }

    public static function paths(array $paths): self
    {
        Assert::notEmpty($paths, 'rollback-by-path');
        foreach ($paths as $i => $p)
            Assert::class($p, StorePath::class, "rollback-by-path[$i]");
        return new self(self::PATHS, $paths);
    }

    private function __construct(
        public readonly string $type,
        public readonly int|DeploymentTag|DateTimeInterface|array $value)
    {
    }
}