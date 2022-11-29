<?php

namespace Cinch\Database;

class UnsupportedVersionException extends \RuntimeException
{
    public function __construct(string $plat, float $version, float $minVersion, string $extra = '')
    {
        if ($extra)
            $extra = "($extra)";
        parent::__construct("unsupported $plat version: min $minVersion, found $version $extra");
    }
}