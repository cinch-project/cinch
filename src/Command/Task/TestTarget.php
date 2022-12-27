<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Command\TaskAttribute;
use Cinch\Common\Dsn;
use Cinch\Database\SessionFactory;

#[TaskAttribute('test target', "connecting to target and initializing session")]
class TestTarget extends Task
{
    public function __construct(private readonly Dsn $dsn, private readonly SessionFactory $sessionFactory)
    {
        parent::__construct();
    }

    protected function doRun(): void
    {
        $this->sessionFactory->create($this->dsn)->close();
    }

    protected function doUndo(): void
    {
    }
}