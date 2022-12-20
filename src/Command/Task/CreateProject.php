<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;

class CreateProject extends Task
{
    public function __construct(private readonly Project $project, private readonly ProjectRepository $projectRepository)
    {
        parent::__construct('create project', $this->project->getId(), 'rollback project');
    }

    protected function doRun(): void
    {
        $this->projectRepository->add($this->project);
    }

    protected function doRollback(): void
    {
        $this->projectRepository->remove($this->project->getId());
    }
}