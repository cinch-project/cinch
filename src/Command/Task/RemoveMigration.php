<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\MigrationStore\StoreDsn;

class RemoveMigration extends Task
{
    private readonly MigrationStore $store;

    public function __construct(
        private readonly StoreDsn $dsn,
        private readonly StorePath $path,
        private readonly MigrationStoreFactory $migrationStoreFactory)
    {
        parent::__construct('remove migration script', 'removing migration script');
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