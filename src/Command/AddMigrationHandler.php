<?php

namespace Cinch\Command;

use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class AddMigrationHandler extends Handler
{
    public function __construct(
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(AddMigration $c): void
    {
        $dsn = $this->projectRepository->get($c->projectId)->getMigrationStoreDsn();
        $this->addTask(new Task\AddMigration($dsn, $c, $this->migrationStoreFactory))->runTasks();
    }
}