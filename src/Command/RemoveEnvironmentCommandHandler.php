<?php

namespace Cinch\Command;

use Cinch\Project\ProjectRepository;
use Exception;

class RemoveEnvironmentCommandHandler implements CommandHandler
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(RemoveEnvironmentCommand $c): void
    {
        if ($c->dropHistory) {
            $env = $c->project->getEnvironmentMap()->get($c->name);
            $this->dataStoreFactory->createHistory($env)->delete();
        }

        $c->project->removeEnvironment($c->name);
        $this->projectRepository->update($c->project);
    }
}