<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\Database\Session;
use Cinch\Database\SessionFactory;
use Cinch\History\ChangeStatus;
use Cinch\History\Deployment;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentError;
use Cinch\History\History;
use Cinch\History\HistoryFactory;
use Cinch\Hook;
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
    private readonly HookRunner $hookRunner;
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
        $this->isSingleTransactionMode = $project->isSingleTransactionMode();

        $this->target = $this->sessionFactory->create($environment->targetDsn);
        $this->deployment = $this->history->createDeployment($deploy->command, $deploy->tag,
            $deploy->deployer, $deploy->isDryRun, $this->isSingleTransactionMode);

        $this->hookRunner = new HookRunner($this->deployment, $project, $this->target, $this->logger, $this->twig);
        $this->hookRunner->run(Hook\Event::AFTER_CONNECT);

        $this->migrationStore = $this->migrationStoreFactory->create($project->getMigrationStoreDsn());
        $this->history = $this->historyFactory->create($environment);
    }

    /**
     * @throws Exception
     */
    protected function deploy(): void
    {
        $isMigrate = $this->deployment->getCommand() == DeploymentCommand::MIGRATE;
        $this->hookRunner->run($isMigrate ? Hook\Event::BEFORE_MIGRATE : Hook\Event::BEFORE_ROLLBACK);

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

        $this->hookRunner->run($isMigrate ? Hook\Event::AFTER_MIGRATE : Hook\Event::AFTER_ROLLBACK);
    }

    /**
     * @returns Task[]
     * @throws Exception
     */
    protected function createDeployTasks(Migration $migration, ChangeStatus $status): array
    {
        $tasks = [];
        $policy = $migration->getScript()->getMigratePolicy();
        $deploy = new Task\Deploy($migration, $status, $this->target, $this->deployment);

        /* BEFORE hooks */
        $event = $this->getEvent($policy, isBefore: true);
        foreach ($this->hookRunner->getHooksForEvent($event) as $hook)
            $tasks[] = new Task\DeployHook($hook, $event, $deploy, $this->hookRunner);

        /* actual deploy task */
        $tasks[] = $deploy;

        /* AFTER hooks */
        $event = $this->getEvent($policy, isBefore: false);
        foreach ($this->hookRunner->getHooksForEvent($event) as $hook)
            $tasks[] = new Task\DeployHook($hook, $event, $deploy, $this->hookRunner);

        return $tasks;
    }

    /**
     * @throws Exception
     */
    private function getEvent(MigratePolicy $policy, bool $isBefore): Hook\Event
    {
        if ($this->deployment->getCommand() == DeploymentCommand::MIGRATE) {
            if ($policy->isOnChange())
                $event = $isBefore ? Hook\Event::BEFORE_ONCHANGE_MIGRATE : Hook\Event::AFTER_ONCHANGE_MIGRATE;
            else if ($policy->isAlways())
                $event = $isBefore ? Hook\Event::BEFORE_ALWAYS_MIGRATE : Hook\Event::AFTER_ALWAYS_MIGRATE;
            else
                $event = $isBefore ? Hook\Event::BEFORE_ONCE_MIGRATE : Hook\Event::AFTER_ONCE_MIGRATE;
        }
        else {
            $event = $isBefore ? Hook\Event::BEFORE_EACH_ROLLBACK : Hook\Event::AFTER_EACH_ROLLBACK; // only supports ONCE migrations
        }

        return $event;
    }
}