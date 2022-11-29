<?php

namespace Cinch\Database;

abstract class Identifier
{
    public function __construct(
        public readonly string $value,
        public readonly string $quotedStr,
        public readonly string $quotedId)
    {
    }

    public function equals(Identifier $id): bool
    {
        return $this->value == $id->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}