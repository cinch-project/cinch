<?php

namespace Cinch\Command;

use Cinch\Command\Task\DeployTask;
use Cinch\Common\Author;
use Cinch\Database\Session;
use Cinch\Database\SessionFactory;
use Cinch\History\ChangeStatus;
use Cinch\History\Deployment;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentError;
use Cinch\History\DeploymentTag;
use Cinch\History\History;
use Cinch\History\HistoryFactory;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\ProjectId;
use Cinch\Project\ProjectRepository;
use Exception;

abstract class DeploymentHandler extends Handler
{
    protected MigrationStore $migrationStore;
    protected History $history;
    private Session $target;
    private Deployment $deployment;
    private DeploymentCommand $command;
    private readonly bool $isSingleTransactionMode;

    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly HistoryFactory $historyFactory,
        private readonly ProjectRepository $projectRepository)
    {
        $command = substr(classname(static::class), 0, -strlen('Handler'));
        $this->command = DeploymentCommand::from(strtolower($command));
    }

    /** Called by deploy() after opening a deployment. */
    protected abstract function runMigrations(): void;

    /**
     * @throws Exception
     */
    protected function prepare(ProjectId $projectId, string $envName): void
    {
        $project = $this->projectRepository->get($projectId);
        $environment = $project->getEnvironmentMap()->get($envName);
        $this->target = $this->sessionFactory->create($environment->targetDsn);
        $this->migrationStore = $this->migrationStoreFactory->create($project->getMigrationStoreDsn());
        $this->history = $this->historyFactory->create($environment);
        $this->isSingleTransactionMode = $project->isSingleTransactionMode();
    }

    /**
     * @throws Exception
     */
    protected function deploy(DeploymentTag $tag, Author $deployer): void
    {
        $error = null;
        $this->deployment = $this->history->openDeployment($this->command, $tag, $deployer, $this->isSingleTransactionMode);

        if ($this->isSingleTransactionMode)
            $this->target->beginTransaction();

        try {
            $this->runMigrations();
            if ($this->isSingleTransactionMode)
                $this->target->commit();
        }
        catch (Exception $e) {
            if ($this->isSingleTransactionMode)
                ignoreException($this->target->rollBack(...));

            $error = DeploymentError::fromException($e);
            throw $e;
        }
        finally {
            /* if we already have an error, ignore exceptions. Otherwise, let them be thrown */
            if ($error)
                ignoreException($this->deployment->close(...), $error);
            else
                $this->deployment->close();
        }
    }

    /** Executes a migration (rollback or migrate) within a transaction.
     * @throws Exception
     */
    protected function runDeployTask(Migration $migration, ChangeStatus $status): void
    {
        $task = new DeployTask($migration, $status, $this->target, $this->deployment, $this->isSingleTransactionMode);
        $task->run();
    }
}