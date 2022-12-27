<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Command\TaskAttribute;
use Cinch\Common\Dsn;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;

#[TaskAttribute('remove migration script', 'removing migration script')]
class RemoveMigration extends Task
{
    private readonly MigrationStore $store;

    public function __construct(
        private readonly Dsn $dsn,
        private readonly StorePath $path,
        private readonly MigrationStoreFactory $migrationStoreFactory)
    {
        parent::__construct();
    }

    protected function doRun(): void
    {
        $this->store = $this->migrationStoreFactory->create($this->dsn);
        $this->store->remove($this->path);
    }

    protected function doUndo(): void
    {
    }
}