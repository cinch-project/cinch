<?php

namespace Cinch\Command\Environment;

use Cinch\Project\Project;

class RemoveEnvironment
{
    public function __construct(
        public readonly Project $project,
        public readonly string $name,
        public readonly bool $dropHistory)
    {
    }
}