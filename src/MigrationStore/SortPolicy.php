<?php

namespace Cinch\MigrationStore;

enum SortPolicy: string
{
    case NATURAL = 'natural';
    case NATURAL_CI = 'natural-ci';
    case ALPHA = 'alpha';
    case ALPHA_CI = 'alpha-ci';

    public function isNatural(): bool
    {
        return $this == self::NATURAL || $this == self::NATURAL_CI;
    }

    public function isCaseInsensitive(): bool
    {
        return $this == self::NATURAL_CI || $this == self::ALPHA_CI;
    }
}
