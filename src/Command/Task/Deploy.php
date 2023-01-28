<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Database\Session;
use Cinch\History\Change;
use Cinch\History\ChangeStatus;
use Cinch\History\Deployment;
use Cinch\History\DeploymentCommand;
use Cinch\MigrationStore\Migration;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class Deploy extends Task
{
    private Change|null $change = null;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly Migration $migration,
        private readonly ChangeStatus $status,
        private readonly Session $target,
        private readonly Deployment $deployment)
    {
        if ($this->deployment->getCommand() == DeploymentCommand::ROLLBACK)
            $name = 'rolling back script';
        else
            $name = 'migrating ' . $this->migration->getScript()->getMigratePolicy()->value . ' script';

        parent::__construct($name, $this->migration->getPath()->value);
    }

    protected function doRun(): void
    {
        $needTransaction = !$this->deployment->isSingleTransactionMode();

        if ($needTransaction)
            $this->target->beginTransaction();

        try {
            if (!$this->deployment->isDryRun()) {
                $command = $this->deployment->getCommand()->value;
                $this->migration->getScript()->$command($this->target);
            }

            $change = $this->createChange();
            $this->deployment->addChange($change);
            $this->change = $change;

            if ($needTransaction)
                $this->target->commit();
        }
        catch (Exception $e) {
            if ($needTransaction) {
                silent_call($this->target->rollBack(...));
                if ($this->change)
                    silent_call($this->deployment->removeChange(...), $this->migration->getPath());
            }

            throw $e;
        }
    }

    protected function doUndo(): void
    {
    }

    /**
     * @throws Exception
     */
    public function getChange(): Change
    {
        /* when change is null, create a temp one (deployedAt is wrong since it has not been deployed yet).
         * This happens for BEFORE hook events (expected). Once deployed, change will be valid/not-null
         * (AFTER hook events). Note: CINCH_CHANGE_DEPLOYED_AT ENV variable is not exposed to BEFORE hooks.
         */
        return $this->change ?? $this->createChange();
    }

    /**
     * @throws Exception
     */
    private function createChange(): Change
    {
        $script = $this->migration->getScript();
        return new Change(
            $this->migration->getPath(),
            $this->deployment->getTag(),
            $script->getMigratePolicy(),
            $this->status,
            $script->getAuthor(),
            $this->migration->getChecksum(),
            $script->getDescription(),
            $script->getLabels(),
            $script->getAuthoredAt(),
            new DateTimeImmutable(timezone: new DateTimeZone('UTC'))
        );
    }
}
