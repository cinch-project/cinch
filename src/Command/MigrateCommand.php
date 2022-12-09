<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\Project\Project;

class MigrateCommand
{
    public function __construct(
        public readonly Project $project,
        public readonly Author $deployer,
        public readonly string|null $tag = null,
        public readonly string $envName = '')
    {
    }
}