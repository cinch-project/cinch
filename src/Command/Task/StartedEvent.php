<?php

namespace Cinch\Command\Task;

use Symfony\Contracts\EventDispatcher\Event;

class StartedEvent extends Event
{
    /**
     * @param int $id task id, should always be sequential starting at 1.
     * @param string $name
     * @param string $description
     * @param bool $isUndo is this an undo task
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $description,
        public readonly bool $isUndo)
    {
    }
}