<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\Component\TemplateEngine\TemplateEngine;
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

abstract class DeployHandler extends Handler
{
    protected readonly MigrationStore $migrationStore;
    protected readonly History $history;
    private readonly Session $target;
    private readonly Deployment $deployment;
    private readonly Hook\Runner $hookRunner;
    private readonly bool $isSingleTransactionMode;

    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly MigrationStoreFactory $migrationStoreFactory,
        private readonly HistoryFactory $historyFactory,
        private readonly ProjectRepository $projectRepository,
        private readonly TemplateEngine $templateEngine)
    {
    }

    /**
     * @throws Exception
     */
    protected function prepare(Deploy $deploy): void
    {
        $project = $this->projectRepository->get($deploy->projectName);
        $environment = $project->getEnvironmentMap()->get($deploy->envName);

        $this->isSingleTransactionMode = $project->isSingleTransactionMode();
        $this->migrationStore = $this->migrationStoreFactory->create($project->getMigrationStoreDsn());
        $this->history = $this->historyFactory->create($environment);
        $this->target = $this->sessionFactory->create($environment->targetDsn);
        $this->deployment = $this->history->createDeployment($deploy->command, $deploy->tag,
            $deploy->deployer, $deploy->isDryRun, $this->isSingleTransactionMode);

        /* not ran as a task. after target database connect. event designed so one can configure db session. In
         * most cases, script hooks are not used since you have no access to target: normally php or sql hooks.
         */
        $this->hookRunner = new Hook\Runner($this->deployment, $project, $this->target, $this->logger, $this->templateEngine);
        $this->hookRunner->run(Hook\Event::AFTER_CONNECT);

        /* add before deploy tasks */
        $isMigrate = $this->deployment->getCommand() == DeploymentCommand::MIGRATE;
        $event = $isMigrate ? Hook\Event::BEFORE_MIGRATE : Hook\Event::BEFORE_ROLLBACK;
        foreach ($this->hookRunner->getHooksForEvent($event) as $hook)
            $this->addTask(new Task\DeployHook($hook, $event, null, $this->hookRunner));
    }

    /**
     * @throws Exception
     */
    protected function deploy(): void
    {
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

        /* append after deploy tasks. derived class should have added their tasks already */
        $isMigrate = $this->deployment->getCommand() == DeploymentCommand::MIGRATE;
        $event = $isMigrate ? Hook\Event::AFTER_MIGRATE : Hook\Event::AFTER_ROLLBACK;
        foreach ($this->hookRunner->getHooksForEvent($event) as $hook)
            $this->addTask(new Task\DeployHook($hook, $event, null, $this->hookRunner));

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
