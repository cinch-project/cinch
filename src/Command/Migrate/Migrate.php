<?php

namespace Cinch\Command\Migrate;

use Cinch\Common\Author;
use Cinch\History\DeploymentTag;
use Cinch\Project\Project;

class Migrate
{
    /**
     * @param Project $project
     * @param DeploymentTag $tag
     * @param Author $deployer
     * @param MigrateOptions $options
     * @param string $envName
     */
    public function __construct(
        public readonly Project $project,
        public readonly DeploymentTag $tag,
        public readonly Author $deployer,
        public readonly MigrateOptions $options,
        public readonly string $envName = '')
    {
    }
}