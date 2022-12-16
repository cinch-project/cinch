<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Symfony\Component\Filesystem\Path;

class StorePath extends SingleValue
{
    const MAX_LENGTH = 512;

    private readonly bool $isSql;

    public function __construct(string $path)
    {
        if (Path::isAbsolute($path))
            throw new AssertException("path must be a relative path, found $path");

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $this->isSql = Assert::in($ext, ['sql', 'php'], 'path extension .php or .sql') == 'sql';

        parent::__construct(Assert::betweenLength($path, 1, self::MAX_LENGTH, message: 'path'));
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