<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\MigrationStore\StoreDsn;

class CreateMigrationStore extends Task
{
    private bool $shouldDelete;
    private MigrationStore $store;

    public function __construct(
        private readonly StoreDsn $dsn,
        private readonly MigrationStoreFactory $migrationStoreFactory)
    {
        $file = MigrationStore::CONFIG_FILE;
        parent::__construct('create migration store', "opening store and creating default '$file' file", canUndo: true);
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->store = $this->migrationStoreFactory->create($this->dsn);
        $this->shouldDelete = $this->store->createConfig();
    }

    protected function doUndo(): void
    {
        if ($this->shouldDelete)
            $this->store->deleteConfig();
    }
}
