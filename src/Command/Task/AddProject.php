<?php

namespace Cinch\Command\Task;

use Cinch\Command\TaskAttribute;
use Cinch\Command\Task;
use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;

#[TaskAttribute('create project', 'create project directory and save configuration', canUndo: true)]
class AddProject extends Task
{
    public function __construct(
        private readonly Project $project,
        private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    protected function doRun(): void
    {
        $this->projectRepository->add($this->project);
    }

    protected function doUndo(): void
    {
        $this->projectRepository->remove($this->project->getId());
    }
}