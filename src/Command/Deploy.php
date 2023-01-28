<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectName;

abstract class Deploy
{
    /**
     * @param DeploymentCommand $command
     * @param ProjectName $projectName
     * @param DeploymentTag $tag
     * @param Author $deployer
     * @param string $envName
     * @param bool $isDryRun
     */
    public function __construct(
        public readonly DeploymentCommand $command,
        public readonly ProjectName $projectName,
        public readonly DeploymentTag $tag,
        public readonly Author $deployer,
        public readonly string $envName,
        public readonly bool $isDryRun)
    {
    }
}
