<?php

namespace Cinch\Hook;

use Cinch\Common\Author;
use Cinch\Database\Session;
use Cinch\History\Change;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectId;
use Cinch\Project\ProjectName;

class HandlerContext
{
    public function __construct(
        public readonly Hook $hook,
        public readonly Change|null $change,
        public readonly Session $target,
        public readonly ProjectId $projectId,
        public readonly ProjectName $projectName,
        public readonly DeploymentTag $tag,
        public readonly DeploymentCommand $command,
        public readonly Author $deployer,
        public readonly string $application,
        public readonly bool $isDryRun,
        public readonly bool $isSingleTransactionMode)
    {
    }
}