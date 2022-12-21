<?php

namespace Cinch\Command\Task;

use Cinch\Command\AddMigration as AddMigrationCommand;
use Cinch\Command\TaskAttribute;
use Cinch\Command\Task;
use Cinch\Common\Dsn;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;

#[TaskAttribute('add migration script', 'adds a migration script using a template', canUndo: true)]
class AddMigration extends Task
{
    private readonly StorePath $path;
    private readonly MigrationStore $store;

    public function __construct(
        private readonly Dsn $dsn,
        private readonly AddMigrationCommand $command,
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
    protected function doUndo(): void
    {
        $this->store->remove($this->path);
    }
}