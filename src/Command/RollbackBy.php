<?php

namespace Cinch\Command;

use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\History\DeploymentTag;
use DateTimeInterface;

class RollbackBy
{
    public const TAG = 'tag';
    public const DATE = 'date';
    public const COUNT = 'count';
    public const SCRIPT = 'script';

    public static function tag(DeploymentTag|null $tag): self
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

    /**
     * @param StorePath[] $paths
     * @return static
     */
    public static function script(array $paths): self
    {
        Assert::notEmpty($paths, 'rollback-by-script');
        foreach ($paths as $i => $p)
            Assert::class($p, StorePath::class, "rollback-by-script[$i]");
        return new self(self::SCRIPT, $paths);
    }

    /**
     * @param string $type
     * @param int|DeploymentTag|DateTimeInterface|array|null $value only TAG supports a null value
     */
    private function __construct(
        public readonly string $type,
        public readonly int|DeploymentTag|DateTimeInterface|array|null $value)
    {
    }
}
