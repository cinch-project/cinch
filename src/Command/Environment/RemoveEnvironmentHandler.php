<?php

namespace Cinch\Command\Environment;

use Cinch\Command\CommandHandler;
use Cinch\Command\DataStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class RemoveEnvironmentHandler implements CommandHandler
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(RemoveEnvironment $c): void
    {
        $project = $this->projectRepository->get($c->projectId);

        if ($c->dropHistory) {
            $env = $project->getEnvironmentMap()->get($c->name);
            $this->dataStoreFactory->createHistory($env)->delete();
        }

        $project->removeEnvironment($c->name);
        $this->projectRepository->update($project);
    }
}