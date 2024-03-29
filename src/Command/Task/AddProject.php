<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;

class AddProject extends Task
{
    public function __construct(
        private readonly Project $project,
        private readonly ProjectRepository $projectRepository)
    {
        parent::__construct('create project', 'creating project directory and saving configuration', canUndo: true);
    }

    protected function doRun(): void
    {
        $this->projectRepository->add($this->project);
    }

    protected function doUndo(): void
    {
        $this->projectRepository->remove($this->project->getName());
    }
}
