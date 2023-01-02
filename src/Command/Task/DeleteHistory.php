<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Common\Environment;
use Cinch\History\HistoryFactory;

class DeleteHistory extends Task
{
    public function __construct(private readonly Environment $env, private readonly HistoryFactory $historyFactory)
    {
        parent::__construct('delete history', 'deleting the history schema');
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->historyFactory->create($this->env)->delete();
    }

    protected function doUndo(): void
    {
    }
}