<?php

namespace Cinch\Command\Event;

use Symfony\Contracts\EventDispatcher\Event;

class TaskEnded extends Event
{
    /**
     * @param bool $success
     * @param float $elapsedSeconds elapsed time in seconds, with nanosecond precision
     */
    public function __construct(public readonly bool $success, public readonly float $elapsedSeconds)
    {
    }
}
