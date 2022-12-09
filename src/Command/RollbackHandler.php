<?php

namespace Cinch\Command;

use Cinch\Database\Session;
use Cinch\History\Change;
use Cinch\History\Command;
use Cinch\History\DeploymentId;
use Cinch\History\History;
use Cinch\History\Status;
use Cinch\MigrationStore\MigrationStore;
use Exception;

class RollbackHandler extends AbstractDeployHandler
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

        $changes = match ($c->type) {
            RollbackType::COUNT => $history->getLastChanges($c->count),
            RollbackType::TAG => $history->getChangesSinceTag($c->tag),
            RollbackType::DATE => $history->getChangesSinceDate($c->date),
            RollbackType::SCRIPT => []//$history->getChangesSinceDate($c->scripts)
        };

        if (count($changes) == 0)
            return;

        $target = $this->dataStoreFactory->createSession($environment->target);
        $migrationStore = $this->dataStoreFactory->createMigrationStore($c->project->getMigrationStore());

        $error = [];
        $deploymentId = $history->startDeployment(Command::ROLLBACK, $c->deployer, $c->tag);

        try {
            $this->rollback($changes, $target, $migrationStore, $history, $deploymentId);
        }
        catch (Exception $e) {
            $error = $this->toDeploymentError($e);
        }
        finally {
            ignoreException(fn() => $history->endDeployment($deploymentId, $error));
        }
    }

    /**
     * @param Change[] $changes
     * @throws Exception
     */
    private function rollback(array $changes, Session $target, MigrationStore $migrationStore,
        History $history, DeploymentId $deploymentId): void
    {
        foreach ($changes as $change) {
            $migration = $migrationStore->getMigration($change->location);

            if (!$change->checksum->equals($migration->checksum))
                throw new Exception("rollback '$change->location' failed: script changed since last migrated");

            $target->beginTransaction();

            try {
                $migration->script->rollback($target);
                $history->addChange($this->createChange($deploymentId, Status::ROLLBACKED, $migration));
                $target->commit();
            }
            catch (Exception $e) {
                ignoreException($target->rollBack(...));
                throw $e;
            }
        }
    }
}