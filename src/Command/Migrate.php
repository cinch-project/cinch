<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectName;

class Migrate extends Deploy
{
    /**
     * @param ProjectName $projectName
     * @param DeploymentTag $tag
     * @param Author $deployer
     * @param MigrateOptions $options
     * @param string $envName
     * @param bool $isDryRun
     */
    public function __construct(
        ProjectName $projectName,
        DeploymentTag $tag,
        Author $deployer,
        public readonly MigrateOptions $options,
        string $envName = '',
        bool $isDryRun = false)
    {
        parent::__construct(DeploymentCommand::MIGRATE, $projectName, $tag, $deployer, $envName, $isDryRun);
    }
}