<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Common\Dsn;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;

class CreateMigrationStore extends Task
{
    private MigrationStore $store;

    public function __construct(
        private readonly Dsn $dsn,
        private readonly MigrationStoreFactory $migrationStoreFactory)
    {
        parent::__construct('create migration store', $this->dsn, canUndo: true);
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