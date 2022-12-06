<?php

namespace Cinch\Command;

use Cinch\Project\Project;

class MigrateCommand
{
    public function __construct(
        public readonly Project $project,
        public readonly string $tag = '',
        public readonly string $envName = '')
    {
    }
}