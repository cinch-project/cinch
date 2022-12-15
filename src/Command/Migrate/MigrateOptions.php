<?php

namespace Cinch\Command\Migrate;

use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;

class MigrateOptions
{
    /**
     * @param StorePath[]|int|null $value array of store paths to migrate or count of migrations to migrate.
     */
    public function __construct(private readonly array|int|null $value = null)
    {
        if (is_array($this->value)) {
            Assert::notEmpty($this->value, 'paths <value>');
            foreach ($this->value as $i => $l)
                if (!($l instanceof StorePath))
                    throw new AssertException("value paths[$i] must be instanceof " . StorePath::class);
        }
        else if (is_int($this->value)) {
            Assert::greaterThan($value, 0, 'count <value>');
        }
    }

    public function getCount(): int|null
    {
        return is_int($this->value) ? $this->value : null;
    }

    /**
     * @return StorePath[]|null
     */
    public function getPaths(): array|null
    {
        return is_array($this->value) ? $this->value : null;
    }
}