<?php

namespace Cinch\Command;

use Cinch\Command\Event\BeforeDeploy;
use Cinch\Database\Session;
use Cinch\Database\SessionFactory;
use Cinch\History\ChangeStatus;
use Cinch\History\Deployment;
use Cinch\History\DeploymentError;
use Cinch\History\History;
use Cinch\History\HistoryFactory;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Project\HookEvent;
use Cinch\Project\Project;
use Cinch\Project\ProjectRepository;
use Exception;

abstract class DeployHandler extends Handler
{
    private readonly Project $project;
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
    protected function prepare(Deploy $deploy): void
    {;
        $this->project = $this->projectRepository->get($deploy->projectId);
        $environment = $this->project->getEnvironmentMap()->get($deploy->envName);

        $this->target = $this->sessionFactory->create($environment->targetDsn);
        $this->migrationStore = $this->migrationStoreFactory->create($this->project->getMigrationStoreDsn());
        $this->history = $this->historyFactory->create($environment);
        $this->isSingleTransactionMode = $this->project->isSingleTransactionMode();
        $this->deployment = $this->history->createDeployment($deploy->command, $deploy->tag,
            $deploy->deployer, $deploy->isDryRun, $this->isSingleTransactionMode);
    }

    /**
     * @throws Exception
     */
    protected function deploy(): void
    {
        $this->dispatcher->dispatch(new BeforeDeploy(
            $this->deployment->getCommand(),
            $this->project,
            $this->target,
            $this->deployment->getTag(),
            $this->deployment->isDryRun()
        ));

        $error = null;
        $this->deployment->open();

        if ($this->isSingleTransactionMode) {
            try {
                $this->target->beginTransaction();
            }
            catch (Exception $e) {
                silent_call($this->deployment->close(...), DeploymentError::fromException($e));
                throw $e;
            }
        }

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

        $this->dispatcher->dispatch(new AfterDeploy(
            $this->deployment->getCommand(),
            $this->project,
            $this->target,
            $this->deployment->getTag(),
            $this->deployment->isDryRun()
        ));
    }

    /**
     * @throws Exception
     */
    protected function createDeployTask(Migration $migration, ChangeStatus $status): Task\Deploy
    {
        return new Task\Deploy($migration, $status, $this->target, $this->deployment);
    }
}