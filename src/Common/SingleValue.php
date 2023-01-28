<?php

namespace Cinch\Common;

abstract class SingleValue
{
    public function __construct(public readonly string|int|float $value)
    {
    }

    public function equals(SingleValue $value): bool
    {
        return get_class($this) == get_class($value) && $this->value === $value->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
