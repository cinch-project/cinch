<?php

namespace Cinch\Command\Task;

use Cinch\Command\AddScript as AddScriptCommand;
use Cinch\Command\Task;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\MigrationStore\StoreDsn;

class AddScript extends Task
{
    private readonly StorePath $path;
    private readonly MigrationStore $store;

    public function __construct(
        private readonly StoreDsn $dsn,
        private readonly AddScriptCommand $command,
        private readonly MigrationStoreFactory $migrationStoreFactory)
    {
        parent::__construct('add migration script', 'adding migration script from template', canUndo: true);
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