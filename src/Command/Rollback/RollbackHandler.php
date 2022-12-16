<?php

namespace Cinch\Command\Rollback;

use Cinch\Command\DeploymentHandler;
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

        $this->changes = match ($c->rollbackBy->type) {
            RollbackBy::COUNT => $view->getMostRecentChangesByCount($c->rollbackBy->value),
            RollbackBy::TAG => $view->getMostRecentChangesSinceTag($c->rollbackBy->value),
            RollbackBy::DATE => $view->getMostRecentChangesSinceDate($c->rollbackBy->value),
            RollbackBy::PATHS => $view->getMostRecentChanges($c->rollbackBy->value, excludeRollbacked: true)
        };

        if (count($this->changes) == 0)
            return;

        $this->deploy($c->tag, $c->deployer);
    }

    /**
     * @throws Exception
     */
    protected function run(): void
    {
        foreach ($this->changes as $change) {
            $migration = $this->migrationStore->get($change->path);

            if (!$change->checksum->equals($migration->checksum))
                throw new Exception("rollback '$change->path' failed: script changed since last migrated");

            $this->execute($migration, ChangeStatus::ROLLBACKED);
        }
    }
}