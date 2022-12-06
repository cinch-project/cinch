<?php

namespace Cinch\Command;

use Cinch\Project\Project;

class RemoveEnvironmentCommand
{
    public function __construct(
        public readonly Project $project,
        public readonly string $name,
        public readonly bool $dropHistory)
    {
    }
}