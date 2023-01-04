<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectId;

class Migrate extends Deploy
{
    /**
     * @param ProjectId $projectId
     * @param DeploymentTag $tag
     * @param Author $deployer
     * @param MigrateOptions $options
     * @param bool $isDryRun
     * @param string $envName
     */
    public function __construct(
        ProjectId $projectId,
        DeploymentTag $tag,
        Author $deployer,
        public readonly MigrateOptions $options,
        bool $isDryRun = false,
        string $envName = '')
    {
        parent::__construct(DeploymentCommand::MIGRATE, $projectId, $tag, $deployer, $isDryRun, $envName);
    }
}