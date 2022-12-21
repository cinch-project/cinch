<?php

namespace Cinch\Command;

use Cinch\Project\Project;

class CreateProject
{
    public function __construct(public readonly Project $project, public readonly string $envName = '')
    {
    }
}