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
     * @param string $envName
     * @param bool $isDryRun
     */
    public function __construct(
        ProjectId $projectId,
        DeploymentTag $tag,
        Author $deployer,
        public readonly MigrateOptions $options,
        string $envName = '',
        bool $isDryRun = false)
    {
        parent::__construct(DeploymentCommand::MIGRATE, $projectId, $tag, $deployer, $envName, $isDryRun);
    }
}