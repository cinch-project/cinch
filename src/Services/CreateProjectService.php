<?php

namespace Cinch\Services;

use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;
use Exception;

class CreateProjectService
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function execute(Project $project, string $environmentName = ''): void
    {
        $rollback = [];
        $environment = $project->getEnvironment($environmentName);

        $this->dataStoreFactory->createSession($environment->target)->close(); // test connection

        try {
            $this->projectRepository->add($project);
            $rollback['projectDir'] = fn() => $this->projectRepository->remove($project->getId());

            $migrationStore = $this->dataStoreFactory->createMigrationStore($project->getMigrationStore());
            $migrationStore->create();
            $rollback['store'] = $migrationStore->delete(...);

            $this->dataStoreFactory->createHistory($environment)->create();
        }
        catch (Exception $e) {
            foreach ($rollback as $name => $action) {
                echo "rollback: $name\n";
                $action();
            }

            throw $e;
        }
    }
}