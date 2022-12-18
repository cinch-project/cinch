<?php

namespace Cinch\Command;

use Cinch\Io;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class CommandHandler
{
    protected readonly Io $io;
    protected readonly EventDispatcherInterface $dispatcher;

    public function setIo(Io $io): void
    {
        $this->io = $io;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }
}