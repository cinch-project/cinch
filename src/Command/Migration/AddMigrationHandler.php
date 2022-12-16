<?php

namespace Cinch\Command\Migration;

use Cinch\Command\CommandHandler;
use Cinch\Command\DataStoreFactory;
use Cinch\Project\ProjectRepository;
use Exception;

class AddMigrationHandler implements CommandHandler
{
    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(AddMigration $c): void
    {
        $this->dataStoreFactory
            ->createMigrationStore($this->projectRepository->get($c->projectId)->getMigrationStoreDsn())
            ->add($c->path, $c->migratePolicy, $c->author, $c->authoredAt, $c->description);
    }
}