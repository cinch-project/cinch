<?php

namespace Cinch\Command;

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
use Cinch\Project\ProjectRepository;
use Exception;
use Twig\Environment as Twig;

abstract class DeployHandler extends Handler
{
    protected readonly MigrationStore $migrationStore;
    protected readonly History $history;
    private readonly Session $target;
    private readonly Deployment $deployment;
    private readonly HookManager $hookManager;
    private readonly bool $isSingleTransactionMode;

    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly HistoryFactory $historyFactory,
        private readonly ProjectRepository $projectRepository,
        private readonly Twig $twig)
    {
    }

    /**
     * @throws Exception
     */
    protected function prepare(Deploy $deploy): void
    {
        $project = $this->projectRepository->get($deploy->projectId);
        $environment = $project->getEnvironmentMap()->get($deploy->envName);

        $this->target = $this->sessionFactory->create($environment->targetDsn);
        $this->migrationStore = $this->migrationStoreFactory->create($project->getMigrationStoreDsn());
        $this->history = $this->historyFactory->create($environment);
        $this->isSingleTransactionMode = $project->isSingleTransactionMode();

        $this->deployment = $this->history->createDeployment($deploy->command, $deploy->tag,
            $deploy->deployer, $deploy->isDryRun, $this->isSingleTransactionMode);

        $this->hookManager = new HookManager($this->deployment, $project, $this->target, $this->logger, $this->twig);
    }

    /**
     * @throws Exception
     */
    protected function deploy(): void
    {
        $this->hookManager->beforeDeploy();

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

        $this->hookManager->afterDeploy();
    }

    /**
     * @throws Exception
     */
    protected function createDeployTask(Migration $migration, ChangeStatus $status): Task\Deploy
    {
        return new Task\Deploy($migration, $status, $this->target, $this->deployment);
    }
}