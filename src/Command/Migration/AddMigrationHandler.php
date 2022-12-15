<?php

namespace Cinch\Command\Migration;

use Cinch\Command\CommandHandler;
use Cinch\Command\DataStoreFactory;
use Exception;

class AddMigrationHandler implements CommandHandler
{
    public function __construct(private readonly DataStoreFactory $dataStoreFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(AddMigration $c): void
    {
        $this->dataStoreFactory
            ->createMigrationStore($c->migrationStoreDsn)
            ->add($c->path, $c->migratePolicy, $c->author, $c->authoredAt, $c->description);
    }
}