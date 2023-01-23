<?php

namespace Cinch\Console\Query;

use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;

class GetProjectHandler extends QueryHandler
{
    public function __construct(private readonly ProjectRepository $projectRepository)
    {
    }

    public function handle(GetProject $getProject): Project
    {
        return $this->projectRepository->get($getProject->projectName);
    }
}