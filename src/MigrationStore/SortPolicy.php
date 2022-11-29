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
        return match ($this) {
            self::NATURAL, self::NATURAL_CI => true,
            default => false
        };
    }

    public function isCaseInsensitive(): bool
    {
        return match ($this) {
            self::NATURAL_CI, self::ALPHA_CI, => true,
            default => false
        };
    }
}