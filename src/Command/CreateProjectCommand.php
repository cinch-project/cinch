<?php

namespace Cinch\Command;

use Cinch\Project\Project;

class CreateProjectCommand
{
    public function __construct(public readonly Project $project, public readonly string $envName = '')
    {
    }
}