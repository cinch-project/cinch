<?php

namespace Cinch\Command\Migration;

use Cinch\Command\CommandHandler;
use Cinch\Command\DataStoreFactory;
use Exception;

class RemoveMigrationHandler implements CommandHandler
{
    public function __construct(private readonly DataStoreFactory $dataStoreFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(RemoveMigration $c): void
    {
        $changes = $this->dataStoreFactory
            ->createHistory($c->environment)
            ->getChangeView()
            ->getMostRecentChanges([$c->location]);

        if ($changes)
            throw new \RuntimeException(sprintf(
                "cannot remove migration '%s': last deployed '%s', status '%s', tag '%s'",
                $c->location,
                $changes[0]->deployedAt->format('Y-m-d H:i:s.uP'),
                $changes[0]->status->value,
                $changes[0]->tag->value,
            ));

        $this->dataStoreFactory->createMigrationStore($c->migrationStoreDsn)->remove($c->location);
    }
}