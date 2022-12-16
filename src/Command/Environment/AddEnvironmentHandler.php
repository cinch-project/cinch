<?php

namespace Cinch\Command\Environment;

use Cinch\Command\CommandHandler;
use Cinch\Command\DataStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class AddEnvironmentHandler implements CommandHandler
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(AddEnvironment $c): void
    {
        $project = $this->projectRepository->get($c->projectId);

        /* fails if $name exists */
        $project->addEnvironment($c->newName, $c->newEnvironment);

        /* test connection */
        $this->dataStoreFactory->createSession($c->newEnvironment->targetDsn)->close();

        /* fails if history exists. can't share history between environments or projects */
        $history = $this->dataStoreFactory->createHistory($c->newEnvironment);
        $history->create();

        try {
            $this->projectRepository->update($project);
        }
        catch (Exception $e) {
            ignoreException($history->delete(...));
            throw $e;
        }
    }
}