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
     * @param bool $isDryRun
     * @param string $envName
     */
    public function __construct(
        ProjectId $projectId,
        DeploymentTag $tag,
        Author $deployer,
        public readonly RollbackBy $rollbackBy,
        bool $isDryRun = false,
        string $envName = '')
    {
        parent::__construct(DeploymentCommand::ROLLBACK, $projectId, $tag, $deployer, $isDryRun, $envName);
    }
}