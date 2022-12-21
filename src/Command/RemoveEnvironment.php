<?php

namespace Cinch\Command;

use Cinch\Project\ProjectId;

class RemoveEnvironment
{
    public function __construct(
        public readonly ProjectId $projectId,
        public readonly string $name,
        public readonly bool $dropHistory)
    {
    }
}