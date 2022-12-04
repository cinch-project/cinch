<?php

namespace Cinch\Services;

use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;
use Exception;

class RemoveEnvironment
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function execute(Project $project, string $name, bool $dropHistory): void
    {
        if ($dropHistory) {
            $env = $project->getEnvironmentMap()->get($name);
            $this->dataStoreFactory->createHistory($env)->delete();
        }

        $project->removeEnvironment($name);
        $this->projectRepository->update($project);
    }
}