<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Project\Project;
use Exception;

class RemoveEnvironment extends Task
{
    /**
     * @throws Exception
     */
    public function __construct(
        private readonly Project $project,
        private readonly string $envName)
    {
        $env = $this->project->getEnvironmentMap()->get($this->envName);

        parent::__construct('remove environment', sprintf('%s: target=%s history=%s schema=%s table_prefix=%s',
            $this->envName,
            $env->targetDsn->getScheme(),
            $env->historyDsn->getScheme(),
            $env->schema ?: "''",
            $env->tablePrefix ?: "''"
        ));
    }

    protected function doRun(): void
    {
        $this->project->removeEnvironment($this->envName);
    }

    protected function doRollback(): void
    {
    }
}