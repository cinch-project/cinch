<?php

namespace Cinch\Command\Task;

use Cinch\Command\TaskAttribute;
use Cinch\Command\Task;
use Cinch\Common\Dsn;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;

#[TaskAttribute('create migration store',
    "opening store and creating the default '" . MigrationStore::CONFIG_FILE . "' file", canUndo: true)]
class CreateMigrationStore extends Task
{
    private MigrationStore $store;

    public function __construct(
        private readonly Dsn $dsn,
        private readonly MigrationStoreFactory $migrationStoreFactory)
    {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->store = $this->migrationStoreFactory->create($this->dsn);
        $this->store->createConfig();
    }

    protected function doUndo(): void
    {
        $this->store->deleteConfig();
    }
}