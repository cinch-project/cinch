<?php

namespace Cinch\Command\Rollback;

use Cinch\Common\Author;
use Cinch\History\DeploymentTag;
use Cinch\Project\Project;

class Rollback
{
    /**
     * @param Project $project
     * @param Author $deployer
     * @param DeploymentTag $tag
     * @param RollbackBy $rollbackBy
     * @param string $envName
     */
    public function __construct(
        public readonly Project $project,
        public readonly Author $deployer,
        public readonly DeploymentTag $tag,
        public readonly RollbackBy $rollbackBy,
        public readonly string $envName = '')
    {
    }
}