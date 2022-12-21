<?php

namespace Cinch\Command;

use Cinch\Database\SessionFactory;
use Cinch\History\HistoryFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class AddEnvironmentHandler extends Handler
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

        $this->addTask(new Task\AddEnvironment($project, $c->newName, $c->newEnvironment))
            ->addTask(new Task\TestTarget($c->newEnvironment->targetDsn, $this->sessionFactory))
            ->addTask(new Task\CreateHistory($c->newEnvironment, $this->historyFactory))
            ->addTask(new Task\UpdateProject($project, $this->projectRepository))
            ->runTasks();
    }
}