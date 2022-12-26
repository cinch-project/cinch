<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\History\ChangeStatus;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\MigrationOutOfSyncException;
use Exception;

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
    protected function runMigrations(): void
    {
        $count = $this->options->getCount();

        foreach ($this->getMigrations() as $migration) {
            if ($migration->getScript()->getMigratePolicy() == MigratePolicy::NEVER ||
                !($status = $this->getStatus($migration))) {
                continue;
            }

            $this->runDeployTask($migration, $status);

            if ($count !== null && --$count == 0)
                break;
        }
    }

    /**
     * @return Migration[]
     * @throws Exception
     */
    private function getMigrations(): array
    {
        /* migrate specific scripts */
        if ($paths = $this->options->getPaths())
            return array_map(fn($p) => $this->migrationStore->get($p), $paths);
        return $this->migrationStore->all();
    }

    /**
     * @param Migration $migration
     * @return ChangeStatus|null change status or null if migration should be skipped
     * @throws Exception
     */
    private function getStatus(Migration $migration): ChangeStatus|null
    {
        $changes = $this->history->getChangeView()->getMostRecentChanges([$migration->getPath()]);

        /* doesn't exist yet, migrate it */
        if (!$changes)
            return ChangeStatus::MIGRATED;

        $change = $changes[0];
        $scriptChanged = !$change->checksum->equals($migration->getChecksum());

        if ($change->migratePolicy == MigratePolicy::ONCE) {
            /* error: migrate once policy cannot change */
            if ($scriptChanged)
                throw new MigrationOutOfSyncException(
                    "once migration '$migration' no longer matches history");

            return null;
        }

        if ($change->status == ChangeStatus::ROLLBACKED) {
            /* error: rollbacked script cannot change */
            if ($scriptChanged)
                throw new MigrationOutOfSyncException(
                    "rollbacked migration '$migration' no longer matches history");

            return null;
        }

        /* only migrate when script changes */
        if (!$scriptChanged && $change->migratePolicy == MigratePolicy::ONCHANGE)
            return null;

        /* migrate policy is ONCHANGE or ALWAYS */
        return ChangeStatus::REMIGRATED;
    }
}