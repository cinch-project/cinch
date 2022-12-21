<?php

namespace Cinch\Command;

use Cinch\Database\SessionFactory;
use Cinch\History\HistoryFactory;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class CreateProjectHandler extends Handler
{
    public function __construct(
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly SessionFactory $sessionFactory,
        private readonly HistoryFactory $historyFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(CreateProject $c): void
    {
        $env = $c->project->getEnvironmentMap()->get($c->envName);

        $this->addTask(new Task\TestTarget($env->targetDsn, $this->sessionFactory))
            ->addTask(new Task\AddProject($c->project, $this->projectRepository))
            ->addTask(new Task\CreateMigrationStore($c->project->getMigrationStoreDsn(), $this->migrationStoreFactory))
            ->addTask(new Task\CreateHistory($env, $this->historyFactory))
            ->runTasks();
    }
}