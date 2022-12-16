<?php

namespace Cinch\Command\Environment;

use Cinch\Command\CommandHandler;
use Cinch\Database\SessionFactory;
use Cinch\History\HistoryFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class AddEnvironmentHandler implements CommandHandler
{
    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly HistoryFactory $historyFactory,
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
        $this->sessionFactory->create($c->newEnvironment->targetDsn)->close();

        /* fails if history exists. can't share history between environments or projects */
        $history = $this->historyFactory->create($c->newEnvironment);
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