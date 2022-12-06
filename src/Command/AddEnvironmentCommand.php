<?php

namespace Cinch\Command;

use Cinch\Common\Environment;
use Cinch\Project\Project;

class AddEnvironmentCommand
{
    public function __construct(
        public readonly Project $project,
        public readonly string $name,
        public readonly Environment $environment
    )
    {
    }
}