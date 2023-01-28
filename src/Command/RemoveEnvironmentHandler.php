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
        $project = $this->projectRepository->get($c->projectName);

        $this->addTask(new Task\RemoveEnvironment($project, $c->name))
            ->addTask(new Task\UpdateProject($project, $this->projectRepository));

        if ($c->deleteHistory) {
            $env = $project->getEnvironmentMap()->get($c->name);
            $this->addTask(new Task\DeleteHistory($env, $this->historyFactory));
        }

        $this->runTasks();
    }
}
