<?php

namespace Cinch\Command\Task;

class TaskStarted
{
    public function __construct(
        public readonly string $name,
        public readonly string $message = '',
        public readonly bool $rollback = false)
    {
    }
}