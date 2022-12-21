<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Common\Dsn;
use Cinch\Database\SessionFactory;

class TestTarget extends Task
{
    public function __construct(private readonly Dsn $dsn, private readonly SessionFactory $sessionFactory)
    {
        parent::__construct('test target', $this->dsn);
    }

    protected function doRun(): void
    {
        $this->sessionFactory->create($this->dsn)->close();
    }

    protected function doUndo(): void
    {
    }
}