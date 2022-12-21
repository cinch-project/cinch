<?php

namespace Cinch\Command;

#[\Attribute(\Attribute::TARGET_CLASS)]
class TaskAttribute
{
    /**
     * @param string $name task name
     * @param string $description brief description of the task
     * @param bool $canUndo true if task can perform an undo, false otherwise
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly bool $canUndo = false)
    {
    }
}