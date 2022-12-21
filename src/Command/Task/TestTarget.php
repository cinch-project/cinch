<?php

namespace Cinch\Command\Task;

use Cinch\Command\TaskAttribute;
use Cinch\Command\Task;
use Cinch\Common\Dsn;
use Cinch\Database\SessionFactory;

#[TaskAttribute('test target', "connect to target, initialize session, disconnect")]
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