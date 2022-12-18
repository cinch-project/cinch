<?php

namespace Cinch\Command\Migration;

use Cinch\Command\CommandHandler;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class AddMigrationHandler extends CommandHandler
{
    public function __construct(
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(AddMigration $c): void
    {
        $this->migrationStoreFactory
            ->create($this->projectRepository->get($c->projectId)->getMigrationStoreDsn())
            ->add($c->path, $c->migratePolicy, $c->author, $c->authoredAt, $c->description);
    }
}