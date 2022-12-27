<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Command\TaskAttribute;
use Cinch\Common\Environment;
use Cinch\Project\Project;

#[TaskAttribute('add environment', 'adding an environment to the project configuration', canUndo: true)]
class AddEnvironment extends Task
{
    public function __construct(
        private readonly Project $project,
        private readonly string $envName,
        private readonly Environment $env)
    {
        parent::__construct();
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