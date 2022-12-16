<?php

namespace Cinch\Command\Migration;

use Cinch\Command\CommandHandler;
use Cinch\Command\DataStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class RemoveMigrationHandler implements CommandHandler
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(RemoveMigration $c): void
    {
        $project = $this->projectRepository->get($c->projectId);

        $changes = $this->dataStoreFactory
            ->createHistory($project->getEnvironmentMap()->get($c->envName))
            ->getChangeView()
            ->getMostRecentChanges([$c->path]);

        if ($changes)
            throw new \RuntimeException(sprintf(
                "cannot remove migration '%s': last deployed '%s', status '%s', tag '%s'",
                $c->path,
                $changes[0]->deployedAt->format('Y-m-d H:i:s.uP'),
                $changes[0]->status->value,
                $changes[0]->tag->value,
            ));

        $dsn = $this->projectRepository->get($c->projectId)->getMigrationStoreDsn();
        $this->dataStoreFactory->createMigrationStore($dsn)->remove($c->path);
    }
}