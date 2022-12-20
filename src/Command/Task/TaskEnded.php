<?php

namespace Cinch\Command\Task;

class TaskEnded
{
    public function __construct(public readonly bool $success, public readonly float $elapsedSeconds = 0)
    {
    }
}