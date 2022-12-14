<?php

namespace Cinch\Command;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
use Cinch\Common\Location;

class RemoveMigrationCommand
{
    public function __construct(
        public readonly Dsn $migrationStoreDsn,
        public readonly Environment $environment,
        public readonly Location $location)
    {
    }
}