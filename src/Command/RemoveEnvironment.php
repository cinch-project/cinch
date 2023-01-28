<?php

namespace Cinch\Command;

use Cinch\Project\ProjectName;

class RemoveEnvironment
{
    public function __construct(
        public readonly ProjectName $projectName,
        public readonly string $name,
        public readonly bool $deleteHistory)
    {
    }
}
