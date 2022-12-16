<?php

namespace Cinch\Command\Project;

use Cinch\Command\CommandHandler;
use Cinch\Database\SessionFactory;
use Cinch\History\HistoryFactory;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class CreateProjectHandler implements CommandHandler
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
        $rollback = [];
        $environment = $c->project->getEnvironmentMap()->get($c->envName);

        $this->sessionFactory->create($environment->targetDsn)->close(); // test connection

        try {
            $this->projectRepository->add($c->project);
            $rollback['project directory'] = fn() => $this->projectRepository->remove($c->project->getId());

            $migrationStore = $this->migrationStoreFactory->create($c->project->getMigrationStoreDsn());
            $migrationStore->createConfig();
            $rollback['migration store'] = $migrationStore->deleteConfig(...);

            $this->historyFactory->create($environment)->create();
        }
        catch (Exception $e) {
            foreach ($rollback as $name => $action) {
                echo "rollback: $name\n";
                ignoreException($action);
            }

            throw $e;
        }
    }
}