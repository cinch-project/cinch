<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;

class UpdateProject extends Task
{
    public function __construct(
        private readonly Project $project,
        private readonly ProjectRepository $projectRepository)
    {
        parent::__construct('update project', 'saving updates to the project configuration', canUndo: true);
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
