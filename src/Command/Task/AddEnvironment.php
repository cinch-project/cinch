<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Common\Environment;
use Cinch\Project\Project;

class AddEnvironment extends Task
{
    public function __construct(
        private readonly Project $project,
        private readonly string $envName,
        private readonly Environment $env)
    {
        parent::__construct('add environment', 'adding environment to project configuration', canUndo: true);
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->project->addEnvironment($this->envName, $this->env);
    }

    protected function doUndo(): void
    {
        $this->project->removeEnvironment($this->envName);
    }
}
