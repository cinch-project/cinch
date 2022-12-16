<?php

namespace Cinch\Command\Migrate;

use Cinch\Command\DeploymentHandler;
use Cinch\Common\MigratePolicy;
use Cinch\History\ChangeStatus;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\MigrationOutOfSyncException;
use Exception;
use Generator;

class MigrateHandler extends DeploymentHandler
{
    private MigrateOptions $options;

    /**
     * @throws Exception
     */
    public function handle(Migrate $c): void
    {
        $this->options = $c->options;
        $this->prepare($c->projectId, $c->envName);
        $this->deploy($c->tag, $c->deployer);
    }

    /**
     * @throws Exception
     */
    protected function run(): void
    {
        $count = $this->options->getCount();

        foreach ($this->iterate() as $migration) {
            if ($migration->script->getMigratePolicy() == MigratePolicy::NEVER ||
                !($status = $this->getStatus($migration))) {
                continue;
            }

            $this->execute($migration, $status);

            if ($count !== null && --$count == 0)
                break;
        }
    }

    /**
     * @return Generator
     * @throws Exception
     */
    private function iterate(): Generator
    {
        /* migrate specific scripts */
        if ($paths = $this->options->getPaths()) {
            foreach ($paths as $path)
                yield $this->migrationStore->get($path);
        }
        else {
            return $this->migrationStore->iterate();
        }
    }

    /**
     * @param Migration $migration
     * @return ChangeStatus|null change status or null if migration should be skipped
     * @throws Exception
     */
    private function getStatus(Migration $migration): ChangeStatus|null
    {
        $changes = $this->history->getChangeView()->getMostRecentChanges([$migration->path]);

        /* doesn't exist yet, migrate it */
        if (!$changes)
            return ChangeStatus::MIGRATED;

        $change = $changes[0];
        $scriptChanged = !$change->checksum->equals($migration->checksum);

        if ($change->migratePolicy == MigratePolicy::ONCE) {
            /* error: migrate once policy cannot change */
            if ($scriptChanged)
                throw new MigrationOutOfSyncException(
                    "once migration '$migration->path' no longer matches history");

            return null;
        }

        if ($change->status == ChangeStatus::ROLLBACKED) {
            /* error: rollbacked script cannot change */
            if ($scriptChanged)
                throw new MigrationOutOfSyncException(
                    "rollbacked migration '$migration->path' no longer matches history");

            return null;
        }

        /* only migrate when script changes */
        if (!$scriptChanged && $change->migratePolicy == MigratePolicy::ONCHANGE)
            return null;

        /* migrate policy is ONCHANGE or ALWAYS */
        return ChangeStatus::REMIGRATED;
    }
}