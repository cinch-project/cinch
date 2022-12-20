<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Common\Environment;
use Cinch\History\History;
use Cinch\History\HistoryFactory;

class CreateHistory extends Task
{
    private History $history;

    public function __construct(private readonly Environment $env, private readonly HistoryFactory $historyFactory)
    {
        parent::__construct('create history', "($env->schema) $env->historyDsn", 'rollback history');
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->history = $this->historyFactory->create($this->env);
        $this->history->create();
    }

    protected function doRollback(): void
    {
        $this->history->delete();
    }
}