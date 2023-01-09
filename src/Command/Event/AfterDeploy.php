<?php

namespace Cinch\Command\Event;

use Cinch\Database\Session;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\Project;
use Symfony\Contracts\EventDispatcher\Event;

class AfterDeploy extends Event
{
    public function __construct(
        public readonly DeploymentCommand $command,
        public readonly Project $project,
        public readonly Session $target,
        public readonly DeploymentTag $tag,
        public readonly bool $isDryRun)
    {
    }
}