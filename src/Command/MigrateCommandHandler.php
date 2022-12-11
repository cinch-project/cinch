<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\Database\Session;
use Cinch\History\ChangeStatus;
use Cinch\History\ChangeView;
use Cinch\History\Deployment;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentError;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\MigrationOutOfSyncException;
use Cinch\MigrationStore\MigrationStore;
use Exception;

class MigrateCommandHandler implements CommandHandler
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

        $error = null;
        $deployment = $history->openDeployment(DeploymentCommand::MIGRATE, $c->deployer, $c->tag);

        try {
            $this->migrate($deployment, $target, $migrationStore, $history->getChangeView());
        }
        catch (Exception $e) {
            $error = DeploymentError::fromException($e);
            throw $e;
        }
        finally {
            ignoreException($deployment->close(...), $error);
        }
    }

    /**
     * @throws Exception
     */
    private function migrate(Deployment $deployment, Session $target,
        MigrationStore $migrationStore, ChangeView $changeView): void
    {
        foreach ($migrationStore->iterateMigrations() as $migration) {
            $target->beginTransaction();

            try {
                if ($status = $this->getStatus($changeView, $migration)) {
                    $migration->script->migrate($target);
                    $deployment->addChange($status, $migration);
                }

                $target->commit();
            }
            catch (Exception $e) {
                ignoreException($target->rollBack(...));
                throw $e;
            }
        }
    }

    /**
     * @param ChangeView $changeView
     * @param Migration $migration
     * @return ChangeStatus|null change status or null if migration should be skipped
     * @throws Exception
     */
    private function getStatus(ChangeView $changeView, Migration $migration): ChangeStatus|null
    {
        $changes = $changeView->getMostRecentChanges([$migration->location]);

        /* doesn't exist yet, migrate it */
        if (!$changes)
            return ChangeStatus::MIGRATED;

        $change = $changes[0];
        $scriptChanged = !$change->checksum->equals($migration->checksum);

        if ($change->migratePolicy == MigratePolicy::ONCE) {
            /* error: migrate once policy cannot change */
            if ($scriptChanged)
                throw new MigrationOutOfSyncException(
                    "once migration '$migration->location' no longer matches history");

            return null;
        }

        if ($change->status == ChangeStatus::ROLLBACKED) {
            /* error: rollbacked script cannot change */
            if ($scriptChanged)
                throw new MigrationOutOfSyncException(
                    "rollbacked migration '$migration->location' no longer matches history");

            return null;
        }

        /* only migrate when script changes */
        if (!$scriptChanged && $change->migratePolicy == MigratePolicy::ONCHANGE)
            return null;

        /* remigrate: policy is ONCHANGE or ALWAYS */
        return ChangeStatus::REMIGRATED;
    }
}