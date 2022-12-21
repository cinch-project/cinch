<?php

namespace Cinch\Command;

use Cinch\History\HistoryFactory;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class RemoveMigrationHandler extends Handler
{
    public function __construct(
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly HistoryFactory $historyFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(RemoveMigration $c): void
    {
        $project = $this->projectRepository->get($c->projectId);

        $changes = $this->historyFactory
            ->create($project->getEnvironmentMap()->get($c->envName))
            ->getChangeView()
            ->getMostRecentChanges([$c->path]);

        if ($change = array_shift($changes))
            throw new \RuntimeException(sprintf(
                "cannot remove migration '%s': last deployed '%s', status '%s', tag '%s'",
                $c->path,
                $change->deployedAt->format('Y-m-d H:i:s.uP'),
                $change->status->value,
                $change->tag->value,
            ));

        $dsn = $this->projectRepository->get($c->projectId)->getMigrationStoreDsn();
        $this->migrationStoreFactory->create($dsn)->remove($c->path);
    }
}