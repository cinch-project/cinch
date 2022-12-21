<?php

namespace Cinch\Command\Task;

use Cinch\Command\AddMigration as AddMigrationCommand;
use Cinch\Command\Task;
use Cinch\Common\Dsn;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;

class AddMigration extends Task
{
    private readonly StorePath $path;
    private readonly MigrationStore $store;
    public function __construct(
        private readonly Dsn $dsn,
        private readonly AddMigrationCommand $command,
        private readonly MigrationStoreFactory $migrationStoreFactory)
    {
        parent::__construct('add migration script', sprintf('%s:%s - %s',
            $this->command->path,
            $this->command->author,
            $this->command->description
        ), 'rollback migration script');
    }

    /**
     * @inheritDoc
     */
    protected function doRun(): void
    {
        $this->store = $this->migrationStoreFactory->create($this->dsn);
        $this->store->add(
            $this->path = $this->command->path,
            $this->command->migratePolicy,
            $this->command->author,
            $this->command->authoredAt,
            $this->command->description,
            $this->command->labels
        );
    }

    /**
     * @inheritDoc
     */
    protected function doRollback(): void
    {
        $this->store->remove($this->path);
    }
}