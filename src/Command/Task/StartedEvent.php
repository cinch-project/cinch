<?php

namespace Cinch\Command\Task;

use Symfony\Contracts\EventDispatcher\Event;

class StartedEvent extends Event
{
    public function __construct(
        public readonly string $name,
        public readonly string $message = '',
        public readonly bool $isRollback = false)
    {
    }
}