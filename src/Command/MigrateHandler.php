<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentError;
use Cinch\History\HistoryView;
use Cinch\History\ChangeStatus;
use Cinch\MigrationStore\Migration;
use Exception;

class MigrateHandler implements CommandHandler
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
        $target = $this->dataStoreFactory->createSession($environment->targetDsn);
        $migrationStore = $this->dataStoreFactory->createMigrationStore($c->project->getMigrationStoreDsn());
        $history = $this->dataStoreFactory->createHistory($environment);
        $view = $history->getView();

        $deployment = $history->openDeployment(DeploymentCommand::MIGRATE, $c->deployer, $c->tag);

        foreach ($migrationStore->iterateMigrations() as $migration) {
            $target->beginTransaction();

            try {
                if ($status = $this->getStatus($view, $migration)) {
                    $migration->script->migrate($target);
                    $deployment->addChange($status, $migration);
                }

                $target->commit();
            }
            catch (Exception $e) {
                ignoreException($target->rollBack(...));
                ignoreException(fn() => $deployment->close(DeploymentError::fromException($e)));
                throw $e;
            }
        }

        $deployment->close();
    }

    /**
     * @param HistoryView $view
     * @param Migration $migration
     * @return ChangeStatus|null change status or null if migration should be skipped
     * @throws Exception
     */
    private function getStatus(HistoryView $view, Migration $migration): ChangeStatus|null
    {
        $changes = $view->getLatestChangeForLocations([$migration->location]);

        /* doesn't exist yet, migrate it */
        if (!$changes)
            return ChangeStatus::MIGRATED;

        $change = $changes[0];
        $scriptChanged = !$change->checksum->equals($migration->checksum);

        if ($change->migratePolicy == MigratePolicy::ONCE) {
            /* error: migrate once policy cannot change */
            if ($scriptChanged)
                ;// error

            return null;
        }

        if ($change->status == ChangeStatus::ROLLBACKED) {
            /* error: rollbacked script cannot change */
            if ($scriptChanged)
                ; //error

            return null;
        }

        /* only migrate when script changes */
        if (!$scriptChanged && $change->migratePolicy == MigratePolicy::ONCHANGE)
            return null;

        /* remigrate: policy is ONCHANGE or ALWAYS */
        return ChangeStatus::REMIGRATED;
    }
}