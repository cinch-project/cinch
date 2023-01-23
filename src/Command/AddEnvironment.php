<?php

namespace Cinch\Command;

use Cinch\Common\Environment;
use Cinch\Project\ProjectName;

class AddEnvironment
{
    public function __construct(
        public readonly ProjectName $projectName,
        public readonly string $newName,
        public readonly Environment $newEnvironment)
    {
    }
}