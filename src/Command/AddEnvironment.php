<?php

namespace Cinch\Command;

use Cinch\Common\Environment;
use Cinch\Project\ProjectId;

class AddEnvironment
{
    public function __construct(
        public readonly ProjectId $projectId,
        public readonly string $newName,
        public readonly Environment $newEnvironment)
    {
    }
}