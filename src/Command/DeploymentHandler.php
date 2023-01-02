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
    private readonly bool $isSingleTransactionMode;

    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly HistoryFactory $historyFactory,
        private readonly ProjectRepository $projectRepository)
    {
    }

    /**
     * @throws Exception
     */
    protected function prepare(ProjectId $projectId, string $envName, DeploymentCommand $command,
        DeploymentTag $tag, Author $deployer): void
    {
        $project = $this->projectRepository->get($projectId);
        $environment = $project->getEnvironmentMap()->get($envName);
        $this->target = $this->sessionFactory->create($environment->targetDsn);
        $this->migrationStore = $this->migrationStoreFactory->create($project->getMigrationStoreDsn());
        $this->history = $this->historyFactory->create($environment);
        $this->isSingleTransactionMode = $project->isSingleTransactionMode();
        $this->deployment = $this->history->createDeployment($command, $tag, $deployer, $this->isSingleTransactionMode);
    }

    /**
     * @throws Exception
     */
    protected function deploy(): void
    {
        $error = null;
        $this->deployment->open();

        if ($this->isSingleTransactionMode)
            $this->target->beginTransaction();

        try {
            $this->runTasks();
            if ($this->isSingleTransactionMode)
                $this->target->commit();
        }
        catch (Exception $e) {
            if ($this->isSingleTransactionMode)
                silent_call($this->target->rollBack(...));

            $error = DeploymentError::fromException($e);
            throw $e;
        }
        finally {
            /* if we already have an error, ignore exceptions. Otherwise, let them be thrown */
            if ($error)
                silent_call($this->deployment->close(...), $error);
            else
                $this->deployment->close();
        }
    }

    /**
     * @throws Exception
     */
    protected function createDeployTask(Migration $migration, ChangeStatus $status): DeployTask
    {
        return new DeployTask($migration, $status, $this->target, $this->deployment, $this->isSingleTransactionMode);
    }
}