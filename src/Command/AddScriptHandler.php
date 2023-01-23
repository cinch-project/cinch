<?php

namespace Cinch\Command;

use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class AddScriptHandler extends Handler
{
    public function __construct(
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(AddScript $c): void
    {
        $dsn = $this->projectRepository->get($c->projectName)->getMigrationStoreDsn();
        $this->addTask(new Task\AddScript($dsn, $c, $this->migrationStoreFactory))->runTasks();
    }
}