<?php

namespace Cinch\Command\Task;

use Cinch\Command\TaskAttribute;
use Cinch\Command\Task;
use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;

#[TaskAttribute('update project', 'saves any changes made to the project configuration', canUndo: true)]
class UpdateProject extends Task
{
    public function __construct(
        private readonly Project $project,
        private readonly ProjectRepository $projectRepository)
    {
        parent::__construct();
    }

    protected function doRun(): void
    {
        $this->projectRepository->update($this->project);
    }

    protected function doUndo(): void
    {
        $this->projectRepository->update($this->project);
    }
}