<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\History\Command;
use Cinch\History\History;
use Cinch\History\Status;
use Cinch\MigrationStore\Migration;
use Exception;

class MigrateHandler extends AbstractDeployHandler
{
    public function __construct(private readonly DataStoreFactory $dataStoreFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(MigrateCommand $c): void
    {
        $environment = $c->project->getEnvironmentMap()->get($c->envName);
        $target = $this->dataStoreFactory->createSession($environment->target);
        $migrationStore = $this->dataStoreFactory->createMigrationStore($c->project->getMigrationStore());
        $history = $this->dataStoreFactory->createHistory($environment);

        $deploymentId = $history->startDeployment(Command::MIGRATE, $c->deployer, $c->tag);

        foreach ($migrationStore->iterateMigrations() as $migration) {
            $target->beginTransaction();

            try {
                if ($status = $this->getStatus($history, $migration)) {
                    $migration->script->migrate($target);
                    $history->addChange($this->createChange($deploymentId, $status, $migration));
                }

                $target->commit();
            }
            catch (Exception $e) {
                ignoreException($target->rollBack(...));
                ignoreException(fn() => $history->endDeployment($deploymentId, $this->toDeploymentError($e)));
                throw $e;
            }
        }

        $history->endDeployment($deploymentId);
    }

    /**
     * @param History $history
     * @param Migration $migration
     * @return Status|null change status or null if migration should be skipped
     * @throws Exception
     */
    private function getStatus(History $history, Migration $migration): Status|null
    {
        $change = $history->getLatestChangeForLocation($migration->location);

        /* doesn't exist yet, migrate it */
        if (!$change)
            return Status::MIGRATED;

        $scriptChanged = !$change->checksum->equals($migration->checksum);

        if ($change->migratePolicy == MigratePolicy::ONCE) {
            /* error: migrate once policy cannot change */
            if ($scriptChanged)
                ;// error

            return null;
        }

        if ($change->status == Status::ROLLBACKED) {
            /* error: rollbacked script cannot change */
            if ($scriptChanged)
                ; //error

            return null;
        }

        /* only migrate when script changes */
        if (!$scriptChanged && $change->migratePolicy == MigratePolicy::ONCHANGE)
            return null;

        /* remigrate: policy is ONCHANGE or ALWAYS */
        return Status::REMIGRATED;
    }
}