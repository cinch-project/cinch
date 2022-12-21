<?php

namespace Cinch\Command\Task;

use Cinch\Command\TaskAttribute;
use Cinch\Command\Task;
use Cinch\Common\Environment;
use Cinch\History\History;
use Cinch\History\HistoryFactory;

#[TaskAttribute('create history', 'connect to history and create cinch schema', canUndo: true)]
class CreateHistory extends Task
{
    private History $history;

    public function __construct(private readonly Environment $env, private readonly HistoryFactory $historyFactory)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->history = $this->historyFactory->create($this->env);
        $this->history->create();
    }

    protected function doUndo(): void
    {
        $this->history->delete();
    }
}