<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\Database\Session;
use Cinch\History\Change;
use Cinch\History\ChangeStatus;
use Cinch\History\Deployment;
use Cinch\History\DeploymentCommand;
use Cinch\History\DeploymentError;
use Cinch\History\DeploymentTag;
use Cinch\History\History;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\MigrationStore;
use Cinch\Project\Project;
use Cinch\Project\ProjectId;
use Cinch\Project\ProjectRepository;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

abstract class DeploymentHandler implements CommandHandler
{
    protected MigrationStore $migrationStore;
    protected History $history;
    private Session $target;
    private Deployment $deployment;
    private DeploymentCommand $command;

    public function __construct(
        private readonly DataStoreFactory $dataStoreFactory,
        private readonly ProjectRepository $projectRepository)
    {
        $command = substr(classname(static::class), 0, -strlen('Handler'));
        $this->command = DeploymentCommand::from(strtolower($command));
    }

    /** Called by deploy() after opening a deployment. */
    protected abstract function run(): void;

    /**
     * @throws Exception
     */
    protected function prepare(ProjectId $projectId, string $envName): void
    {
        $project = $this->projectRepository->get($projectId);
        $environment = $project->getEnvironmentMap()->get($envName);
        $this->target = $this->dataStoreFactory->createSession($environment->targetDsn);
        $this->migrationStore = $this->dataStoreFactory->createMigrationStore($project->getMigrationStoreDsn());
        $this->history = $this->dataStoreFactory->createHistory($environment);
    }

    /**
     * @throws Exception
     */
    protected function deploy(DeploymentTag $tag, Author $deployer): void
    {
        $error = null;
        $this->deployment = $this->history->openDeployment($this->command, $tag, $deployer);

        try {
            $this->run();
        }
        catch (Exception $e) {
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
    protected function execute(Migration $migration, ChangeStatus $status): void
    {
        $this->target->beginTransaction();

        try {
            $migration->script->{$this->command->value}($this->target);
            $this->addChange($status, $migration);
            $this->target->commit();
        }
        catch (Exception $e) {
            ignoreException($this->target->rollBack(...));
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    private function addChange(ChangeStatus $status, Migration $migration): void
    {
        $this->deployment->addChange(new Change(
            $migration->path,
            $this->deployment->getTag(),
            $migration->script->getMigratePolicy(),
            $status,
            $migration->script->getAuthor(),
            $migration->checksum,
            $migration->script->getDescription(),
            $migration->script->getLabels(),
            $migration->script->getAuthoredAt(),
            new DateTimeImmutable(timezone: new DateTimeZone('UTC'))
        ));
    }
}