<?php

namespace Cinch\Common;

enum MigratePolicy: string
{
    case ONCE = 'once';
    case ALWAYS_BEFORE = 'always-before';
    case ALWAYS_AFTER = 'always-after';
    case ONCHANGE_BEFORE = 'onchange-before';
    case ONCHANGE_AFTER = 'onchange-after';

    public function isBefore(): bool
    {
        return $this == self::ALWAYS_BEFORE || $this == self::ONCHANGE_BEFORE;
    }

    public function isAfter(): bool
    {
        return $this == self::ALWAYS_AFTER || $this == self::ONCHANGE_AFTER;
    }

    public function isAlways(): bool
    {
        return $this == self::ALWAYS_BEFORE || $this == self::ALWAYS_AFTER;
    }

    public function isOnChange(): bool
    {
        return $this == self::ONCHANGE_BEFORE || $this == self::ONCHANGE_AFTER;
    }
}
