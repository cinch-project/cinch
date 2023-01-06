<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectId;

class Rollback extends Deploy
{
    /**
     * @param ProjectId $projectId
     * @param DeploymentTag $tag
     * @param Author $deployer
     * @param RollbackBy $rollbackBy
     * @param string $envName
     * @param bool $isDryRun
     */
    public function __construct(
        ProjectId $projectId,
        DeploymentTag $tag,
        Author $deployer,
        public readonly RollbackBy $rollbackBy,
        string $envName = '',
        bool $isDryRun = false)
    {
        parent::__construct(DeploymentCommand::ROLLBACK, $projectId, $tag, $deployer, $envName, $isDryRun);
    }
}