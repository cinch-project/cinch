<?php

namespace Cinch\Command;

use Cinch\Common\StorePath;
use Cinch\Project\ProjectName;

class RemoveScript
{
    public function __construct(
        public readonly ProjectName $projectName,
        public readonly string $envName,
        public readonly StorePath $path)
    {
    }
}