<?php

namespace Cinch\Command;

use Cinch\Project\ProjectRepository;
use Exception;

class CreateProjectCommandHandler implements CommandHandler
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(CreateProjectCommand $c): void
    {
        $rollback = [];
        $environment = $c->project->getEnvironmentMap()->get($c->envName);

        $this->dataStoreFactory->createSession($environment->targetDsn)->close(); // test connection

        try {
            $this->projectRepository->add($c->project);
            $rollback['project directory'] = fn() => $this->projectRepository->remove($c->project->getId());

            $migrationStore = $this->dataStoreFactory->createMigrationStore($c->project->getMigrationStoreDsn());
            $migrationStore->createConfig();
            $rollback['migration store'] = $migrationStore->deleteConfig(...);

            $this->dataStoreFactory->createHistory($environment)->create();
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