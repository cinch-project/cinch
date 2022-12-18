<?php

namespace Cinch\Console\Query;

use Cinch\Io;

abstract class QueryHandler
{
    protected readonly Io $io;

    public function setIo(Io $io): void
    {
        $this->io = $io;
    }
}