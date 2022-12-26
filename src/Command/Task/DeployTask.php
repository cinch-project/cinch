<?php

namespace Cinch\Command\Task;

use Cinch\Command\Task;
use Cinch\Command\TaskAttribute;
use Cinch\Database\Session;
use Cinch\History\Change;
use Cinch\History\ChangeStatus;
use Cinch\History\Deployment;
use Cinch\MigrationStore\Migration;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

#[TaskAttribute('deploy', 'performing deployment')]
class DeployTask extends Task
{
    private string $command;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly Migration $migration,
        private readonly ChangeStatus $status,
        private readonly Session $target,
        private readonly Deployment $deployment,
        private readonly bool $isSingleTransactionMode)
    {
        parent::__construct();

        $this->command = $this->status != ChangeStatus::ROLLBACKED ? 'migrate' : 'rollback';
        $policy = $this->command == 'migrate' ? $this->migration->getScript()->getMigratePolicy()->value : '';

        /* special case: override TaskAttribute settings */
        $this->setName("$this->command $policy"); // ex: 'migrate once, 'migrate always', 'rollback', etc.
        $this->setDescription($this->migration->getPath());
    }

    protected function doRun(): void
    {
        $addedChange = false;

        try {
            if (!$this->isSingleTransactionMode)
                $this->target->beginTransaction();

            $this->migration->getScript()->{$this->command}($this->target);
            $addedChange = $this->addChange($this->status, $this->migration);

            if (!$this->isSingleTransactionMode)
                $this->target->commit();
        }
        catch (Exception $e) {
            if (!$this->isSingleTransactionMode) {
                ignoreException($this->target->rollBack(...));
                if ($addedChange)
                    ignoreException($this->deployment->removeChange(...), $this->migration->getPath());
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
    private function addChange(ChangeStatus $status, Migration $migration): bool
    {
        $script = $migration->getScript();

        $this->deployment->addChange(new Change(
            $migration->getPath(),
            $this->deployment->getTag(),
            $script->getMigratePolicy(),
            $status,
            $script->getAuthor(),
            $migration->getChecksum(),
            $script->getDescription(),
            $script->getLabels(),
            $script->getAuthoredAt(),
            new DateTimeImmutable(timezone: new DateTimeZone('UTC'))
        ));

        return true;
    }
}