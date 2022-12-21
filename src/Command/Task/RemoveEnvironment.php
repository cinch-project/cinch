<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Common\Environment;
use Cinch\Project\Project;
use Exception;

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

        parent::__construct('remove environment', sprintf('%s: target=%s history=%s schema=%s table_prefix=%s',
            $this->envName,
            $this->env->targetDsn->getScheme(),
            $this->env->historyDsn->getScheme(),
            $this->env->schema ?: "''",
            $this->env->tablePrefix ?: "''"
        ), canUndo: true);
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