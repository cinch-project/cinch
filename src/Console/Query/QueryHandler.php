<?php

namespace Cinch\Console\Query;

use Psr\Log\LoggerInterface;

abstract class QueryHandler
{
    protected readonly LoggerInterface $logger;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}