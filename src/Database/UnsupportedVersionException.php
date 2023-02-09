<?php

namespace Cinch\Database;

use RuntimeException;

class UnsupportedVersionException extends RuntimeException
{
    public function __construct(string $plat, string $version, string $minVersion, string $extra = '')
    {
        if ($extra)
            $extra = "($extra)";
        parent::__construct("unsupported $plat version: min $minVersion, found $version $extra");
    }
}
