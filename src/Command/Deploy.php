<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectId;

abstract class Deploy
{
    /**
     * @param DeploymentCommand $command
     * @param ProjectId $projectId
     * @param DeploymentTag $tag
     * @param Author $deployer
     * @param bool $isDryRun
     * @param string $envName
     */
    public function __construct(
        public readonly DeploymentCommand $command,
        public readonly ProjectId $projectId,
        public readonly DeploymentTag $tag,
        public readonly Author $deployer,
        public readonly bool $isDryRun = false,
        public readonly string $envName = '')
    {
    }
}