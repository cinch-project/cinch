<?php

namespace Cinch\Command\Environment;

use Cinch\Common\Environment;
use Cinch\Project\Project;

class AddEnvironment
{
    public function __construct(
        public readonly Project $project,
        public readonly string $name,
        public readonly Environment $environment
    )
    {
    }
}