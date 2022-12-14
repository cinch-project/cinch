<?php

namespace Cinch\Command\Migrate;

use Cinch\Common\Location;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;

class MigrateOptions
{
    /**
     * @param Location[]|int|null $value array of locations to migrate or count of migrations to migrate.
     */
    public function __construct(private readonly array|int|null $value = null)
    {
        if (is_array($this->value)) {
            Assert::notEmpty($this->value, 'locations <value>');
            foreach ($this->value as $i => $l)
                if (!($l instanceof Location))
                    throw new AssertException("value locations[$i] must be instanceof " . Location::class);
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
     * @return Location[]|null
     */
    public function getLocations(): array|null
    {
        return is_array($this->value) ? $this->value : null;
    }
}