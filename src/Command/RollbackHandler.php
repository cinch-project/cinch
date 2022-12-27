<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\History\Change;
use Cinch\History\ChangeStatus;
use Exception;

class RollbackHandler extends DeploymentHandler
{
    /** @var Change[] */
    private array $changes;

    /**
     * @throws Exception
     */
    public function handle(Rollback $c): void
    {
        $this->prepare($c->projectId, $c->envName);
        $view = $this->history->getChangeView();

        $value = $c->rollbackBy->value;
        $policies = [MigratePolicy::ONCE];    /* rollback only supports 'once' migrations */
        $statuses = [ChangeStatus::MIGRATED]; /* all other statuses are not supported by rollback */

        $this->changes = match ($c->rollbackBy->type) {
            RollbackBy::COUNT => $view->getMostRecentChangesByCount($value, $policies, $statuses),
            RollbackBy::TAG => $view->getMostRecentChangesSinceTag($value, $policies, $statuses),
            RollbackBy::DATE => $view->getMostRecentChangesSinceDate($value, $policies, $statuses),
            RollbackBy::PATHS => $view->getMostRecentChanges($value, $policies, $statuses)
        };

        if (count($this->changes) == 0) {
            $this->logger->debug("rollback-by-{$c->rollbackBy->type}: no changes to rollback");
            return;
        }

        $this->deploy($c->tag, $c->deployer);
    }

    /**
     * @throws Exception
     */
    protected function runMigrations(): void
    {
        foreach ($this->changes as $change) {
            $migration = $this->migrationStore->get($change->path);

            if (!$change->checksum->equals($migration->getChecksum()))
                $this->logger->error("'$migration' cannot be rollbacked, it has changed since the last migrate");
            else
                $this->addTask($this->createDeployTask($migration, ChangeStatus::ROLLBACKED));
        }

        $this->logger->notice(sprintf('found %d eligible migrations for rollback out of %d',
            $this->getTaskCount(), count($this->changes)));

        $this->changes = [];
        $this->runTasks();
    }
}