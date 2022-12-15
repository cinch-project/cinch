<?php

namespace Cinch\Command\Migration;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
use Cinch\Common\StorePath;

class RemoveMigration
{
    public function __construct(
        public readonly Dsn $migrationStoreDsn,
        public readonly Environment $environment,
        public readonly StorePath $path)
    {
    }
}