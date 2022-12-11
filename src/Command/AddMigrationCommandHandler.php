<?php

namespace Cinch\Command;

use Exception;

class AddMigrationCommandHandler implements CommandHandler
{
    public function __construct(private readonly DataStoreFactory $dataStoreFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(AddMigrationCommand $c): void
    {
        $this->dataStoreFactory
            ->createMigrationStore($c->migrationStoreDsn)
            ->addMigration($c->location, $c->migratePolicy, $c->author, $c->authoredAt, $c->description);
    }
}