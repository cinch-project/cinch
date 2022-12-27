<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\History\Change;
use Cinch\History\ChangeStatus;
use Cinch\MigrationStore\Migration;
use Exception;

class MigrateHandler extends DeploymentHandler
{
    private MigrateOptions $migrateOptions;

    /**
     * @throws Exception
     */
    public function handle(Migrate $c): void
    {
        $this->migrateOptions = $c->options;
        $this->prepare($c->projectId, $c->envName);
        $this->deploy($c->tag, $c->deployer);
    }

    /**
     * @throws Exception
     */
    protected function runMigrations(): void
    {
        $changes = [];
        $paths = $this->migrateOptions->getPaths() ?? [];

        /* convert indexed array to assoc. with path keys */
        foreach ($this->history->getChangeView()->getMostRecentChanges($paths) as $c)
            $changes[mb_strtolower($c->path, 'UTF-8')] = $c;

        $beforeTasks = [];
        $onceTasks = [];
        $afterTasks = [];
        $count = $this->migrateOptions->getCount();

        if ($paths)
            $migrations = array_map(fn($p) => $this->migrationStore->get($p), $paths);
        else
            $migrations = $this->migrationStore->all();

        /* find eligible migrations (still filtering). also put them in the before/after order */
        foreach ($migrations as $migration) {
            $change = $changes[mb_strtolower($migration->getPath(), 'UTF-8')] ?? null;

            if (!($status = $this->getStatus($migration, $change)))
                continue;

            $task = $this->createDeployTask($migration, $status);
            $migratePolicy = $migration->getScript()->getMigratePolicy();

            if ($migratePolicy->isBefore()) {
                $beforeTasks[] = $task;
            }
            else if ($migratePolicy->isAfter()) {
                $afterTasks[] = $task;
            }
            else {
                $onceTasks[] = $task;
                if ($count !== null && --$count == 0) // limit to count, doesn't apply to before|after
                    break;
            }
        }

        $this->logger->notice(sprintf("found %d before, %d new, %d after eligible migrations out of %d",
            count($beforeTasks), count($onceTasks), count($afterTasks), count($migrations)));

        foreach ($beforeTasks as $task)
            $this->addTask($task);

        foreach ($onceTasks as $task)
            $this->addTask($task);

        foreach ($afterTasks as $task)
            $this->addTask($task);

        unset($migrations, $beforeTasks, $onceTasks, $afterTasks, $changes);
        $this->runTasks();
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

        /* migrate policy must be always-* or onchange-*, this is a remigrate */
        return ChangeStatus::REMIGRATED;
    }
}