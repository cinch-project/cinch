<?php

namespace Cinch\Command\Task;

use Symfony\Contracts\EventDispatcher\Event;

class EndedEvent extends Event
{
    public function __construct(public readonly bool $success, public readonly float $elapsedSeconds = 0)
    {
    }
}