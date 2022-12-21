<?php

namespace Cinch\Command;

use Cinch\Common\StorePath;
use Cinch\Project\ProjectId;

class RemoveMigration
{
    public function __construct(
        public readonly ProjectId $projectId,
        public readonly string $envName,
        public readonly StorePath $path)
    {
    }
}