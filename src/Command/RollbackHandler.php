<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\Console\ConsoleLogger;
use Cinch\History\ChangeStatus;
use Cinch\History\DeploymentTag;
use Exception;

class RollbackHandler extends DeployHandler
{
    /**
     * @throws Exception
     */
    public function handle(Rollback $c): void
    {
        $this->prepare($c);
        $changes = $this->getChanges($c->rollbackBy);

        foreach ($changes as $change) {
            $migration = $this->migrationStore->get($change->path);

            if (!$change->checksum->equals($migration->getChecksum()))
                $this->logger->error("'$migration' cannot be rollbacked, it has changed since the " .
                    "last deployment on {date}", ['date' => $change->deployedAt]);
            else
                $this->addTask($this->createDeployTask($migration, ChangeStatus::ROLLBACKED));
        }

        if ($this->getTaskCount() == 0) {
            $this->logger->info("nothing to roll back");
            return;
        }

        $this->logger->notice(sprintf('found %d changes for rollback out of %d', $this->getTaskCount(), count($changes)));

        unset($changes);
        $this->deploy();
    }

    /**
     * @throws Exception
     */
    private function getChanges(RollbackBy $rollbackBy): array
    {
        $value = $rollbackBy->value;
        $view = $this->history->getChangeView();

        /* special (common) case when no tag was provided for rollback-by-tag */
        if ($value === null) {
            if (($tag = $view->findFirstRollbackToTag()) === null)
                return [];
            $value = new DeploymentTag($tag);
        }

        $policies = [MigratePolicy::ONCE];    /* rollback only supports 'once' migrations */
        $statuses = [ChangeStatus::MIGRATED]; /* rollback only supports the 'migrated' status */

        return match ($rollbackBy->type) {
            RollbackBy::COUNT => $view->getMostRecentChangesByCount($value, $policies, $statuses),
            RollbackBy::TAG => $view->getMostRecentChangesSinceTag($value, $policies, $statuses),
            RollbackBy::DATE => $view->getMostRecentChangesSinceDate($value, $policies, $statuses),
            RollbackBy::SCRIPT => $view->getMostRecentChanges($value, $policies, $statuses)
        };
    }
}