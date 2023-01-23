<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectName;

class Rollback extends Deploy
{
    /**
     * @param ProjectName $projectName
     * @param DeploymentTag $tag
     * @param Author $deployer
     * @param RollbackBy $rollbackBy
     * @param string $envName
     * @param bool $isDryRun
     */
    public function __construct(
        ProjectName $projectName,
        DeploymentTag $tag,
        Author $deployer,
        public readonly RollbackBy $rollbackBy,
        string $envName = '',
        bool $isDryRun = false)
    {
        parent::__construct(DeploymentCommand::ROLLBACK, $projectName, $tag, $deployer, $envName, $isDryRun);
    }
}