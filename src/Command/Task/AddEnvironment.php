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
        parent::__construct('add environment', sprintf('%s: target=%s history=%s schema=%s table_prefix=%s',
            $this->envName,
            $this->env->targetDsn->getScheme(),
            $this->env->historyDsn->getScheme(),
            $this->env->schema ?: "''",
            $this->env->tablePrefix ?: "''"
        ));
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
    }
}