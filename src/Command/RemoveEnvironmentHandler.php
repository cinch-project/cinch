<?php

namespace Cinch\Command;

use Cinch\History\HistoryFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class RemoveEnvironmentHandler extends Handler
{
    public function __construct(
        private readonly HistoryFactory $historyFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(RemoveEnvironment $c): void
    {
        $project = $this->projectRepository->get($c->projectId);

        if ($c->deleteHistory)
            $this->addTask(new Task\DeleteHistory(
                $project->getEnvironmentMap()->get($c->name),
                $this->historyFactory
            ));

        $this->addTask(new Task\RemoveEnvironment($project, $c->name))
            ->addTask(new Task\UpdateProject($project, $this->projectRepository))
            ->runTasks();
    }
}