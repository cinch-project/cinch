<?php

namespace Cinch\Services;

use Cinch\Common\Environment;
use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;
use Exception;

class AddEnvironment
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function execute(Project $project, string $name, Environment $environment): void
    {
        /* fails if $name exists */
        $project->addEnvironment($name, $environment);

        /* test connection */
        $this->dataStoreFactory->createSession($environment->target)->close();

        /* fails if history exists. can't share history between environments or projects */
        $history = $this->dataStoreFactory->createHistory($environment);
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