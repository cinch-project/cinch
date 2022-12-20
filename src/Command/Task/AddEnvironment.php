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
        $target = $env->targetDsn->getScheme();
        $history = $env->historyDsn->getScheme();
        parent::__construct('add environment', "$envName: target=$target, history=$history");
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->project->addEnvironment($this->envName, $this->env);
    }

    protected function doRollback(): void
    {
    }
}