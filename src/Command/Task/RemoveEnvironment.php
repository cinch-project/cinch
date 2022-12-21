<?php

namespace Cinch\Command\Task;

use Cinch\Command\TaskAttribute;
use Cinch\Command\Task;
use Cinch\Common\Environment;
use Cinch\Project\Project;
use Exception;

#[TaskAttribute('remove environment', 'removes an environment from the project configuration', canUndo: true)]
class RemoveEnvironment extends Task
{
    private readonly Environment $env;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly Project $project,
        private readonly string $envName)
    {
        $this->env = $this->project->getEnvironmentMap()->get($this->envName);
        parent::__construct();
    }

    protected function doRun(): void
    {
        $this->project->removeEnvironment($this->envName);
    }

    protected function doUndo(): void
    {
        $this->project->addEnvironment($this->envName, $this->env);
    }
}