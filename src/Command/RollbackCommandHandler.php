<?php

namespace Cinch\Command;

use Cinch\Database\Session;
use Cinch\History\Change;
use Cinch\History\ChangeStatus;
use Cinch\History\Deployment;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentError;
use Cinch\MigrationStore\MigrationStore;
use Exception;

class RollbackCommandHandler implements CommandHandler
{
    public function __construct(private readonly DataStoreFactory $dataStoreFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(RollbackCommand $c): void
    {
        $environment = $c->project->getEnvironmentMap()->get($c->envName);
        $history = $this->dataStoreFactory->createHistory($environment);
        $changeView = $history->getChangeView();

        $changes = match ($c->rollbackBy->type) {
            RollbackBy::COUNT => $changeView->getMostRecentChangesByCount($c->rollbackBy->value),
            RollbackBy::TAG => $changeView->getMostRecentChangesSinceTag($c->rollbackBy->value),
            RollbackBy::DATE => $changeView->getMostRecentChangesSinceDate($c->rollbackBy->value),
            RollbackBy::SCRIPT => $changeView->getMostRecentChanges($c->rollbackBy->value, excludeRollbacked: true)
        };

        if (count($changes) == 0)
            return;

        $target = $this->dataStoreFactory->createSession($environment->targetDsn);
        $migrationStore = $this->dataStoreFactory->createMigrationStore($c->project->getMigrationStoreDsn());

        $error = null;
        $deployment = $history->openDeployment(DeploymentCommand::ROLLBACK, $c->deployer);

        try {
            $this->rollback($deployment, $target, $migrationStore, $changes);
        }
        catch (Exception $e) {
            $error = DeploymentError::fromException($e);
            throw $e;
        }
        finally {
            ignoreException(fn() => $deployment->close($error));
        }
    }

    /**
     * @param Change[] $changes
     * @throws Exception
     */
    private function rollback(Deployment $deployment, Session $target,
        MigrationStore $migrationStore, array $changes): void
    {
        foreach ($changes as $change) {
            $migration = $migrationStore->getMigration($change->location);

            if (!$change->checksum->equals($migration->checksum))
                throw new Exception("rollback '$change->location' failed: script changed since last migrated");

            $target->beginTransaction();

            try {
                $migration->script->rollback($target);
                $deployment->addChange(ChangeStatus::ROLLBACKED, $migration);
                $target->commit();
            }
            catch (Exception $e) {
                ignoreException($target->rollBack(...));
                throw $e;
            }
        }
    }
}