<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\History\Change;
use Cinch\History\ChangeStatus;
use Cinch\MigrationStore\Migration;
use Exception;

class MigrateHandler extends DeployHandler
{
    /**
     * @throws Exception
     */
    public function handle(Migrate $c): void
    {
        $this->prepare($c);

        $changes = [];
        $paths = $c->options->getPaths() ?? [];

        foreach ($this->history->getChangeView()->getMostRecentChanges($paths) as $change)
            $changes[mb_strtolower($change->path, 'UTF-8')] = $change;

        $beforeTasks = [];
        $onceTasks = [];
        $afterTasks = [];
        $count = $c->options->getCount();

        if (!$changes)
            $migrations = [];
        else if ($paths)
            $migrations = array_map(fn($p) => $this->migrationStore->get($p), $paths);
        else
            $migrations = $this->migrationStore->all();

        /* find eligible migrations (still filtering). also put them in before/after order */
        foreach ($migrations as $migration) {
            $change = $changes[mb_strtolower($migration->getPath(), 'UTF-8')] ?? null;

            if (!($status = $this->getStatus($migration, $change)))
                continue;

            /* in most cases: getScript() must actually load the script, which could be a remote API call. However,
             * at this point it is "known" that this script should be deployed. This avoids wasting API calls on
             * scripts that will ultimately be skipped.
             */
            $migratePolicy = $migration->getScript()->getMigratePolicy();
            $task = $this->createDeployTask($migration, $status);

            if ($migratePolicy->isBefore()) {
                $beforeTasks[] = $task;
            }
            else if ($migratePolicy->isAfter()) {
                $afterTasks[] = $task;
            }
            else {
                $onceTasks[] = $task;
                if ($count !== null && --$count == 0) // limit to once, doesn't apply to before|after
                    break;
            }
        }

        foreach ($beforeTasks as $task)
            $this->addTask($task);

        foreach ($onceTasks as $task)
            $this->addTask($task);

        foreach ($afterTasks as $task)
            $this->addTask($task);

        if ($this->getTaskCount() == 0) {
            $this->logger->info("nothing to migrate");
            return;
        }

        $this->logger->notice(sprintf("found %d before, %d new, %d after eligible migrations out of %d",
            count($beforeTasks), count($onceTasks), count($afterTasks), count($migrations)));

        unset($migrations, $beforeTasks, $onceTasks, $afterTasks, $changes);
        $this->deploy();
    }

    /**
     * @param Migration $migration
     * @param Change|null $change
     * @return ChangeStatus|null change status or null if migration should be skipped
     */
    private function getStatus(Migration $migration, Change|null $change): ChangeStatus|null
    {
        /* doesn't exist yet, migrate it */
        if (!$change)
            return ChangeStatus::MIGRATED;

        $scriptChanged = !$change->checksum->equals($migration->getChecksum());

        if ($change->migratePolicy == MigratePolicy::ONCE) {
            if ($scriptChanged)
                $this->logger->error("once migration '$migration' no longer matches history");
            return null;
        }

        if ($change->status == ChangeStatus::ROLLBACKED) {
            if ($scriptChanged)
                $this->logger->error("rollbacked migration '$migration' no longer matches history");
            return null;
        }

        /* only migrate when script changes */
        if (!$scriptChanged && $change->migratePolicy->isOnChange()) {
            $this->logger->notice(sprintf("skipping unchanged migration '%s': migrate_policy '%s'",
                $migration, $change->migratePolicy->value));
            return null;
        }

        /* migrate policy must be always-* or onchange-*, this a remigrate */
        return ChangeStatus::REMIGRATED;
    }
}