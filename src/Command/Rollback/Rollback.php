<?php

namespace Cinch\Command\Rollback;

use Cinch\Common\Author;
use Cinch\History\DeploymentTag;
use Cinch\Project\ProjectId;

class Rollback
{
    /**
     * @param ProjectId $projectId
     * @param Author $deployer
     * @param DeploymentTag $tag
     * @param RollbackBy $rollbackBy
     * @param string $envName
     */
    public function __construct(
        public readonly ProjectId $projectId,
        public readonly Author $deployer,
        public readonly DeploymentTag $tag,
        public readonly RollbackBy $rollbackBy,
        public readonly string $envName = '')
    {
    }
}