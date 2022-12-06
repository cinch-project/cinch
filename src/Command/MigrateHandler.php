<?php

namespace Cinch\Command;

use Cinch\Common\MigratePolicy;
use Cinch\History\Change;
use Cinch\History\ChangeId;
use Cinch\History\Command;
use Cinch\History\DeploymentId;
use Cinch\History\History;
use Cinch\History\Status;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\Script\CanMigrate;
use Cinch\MigrationStore\Script\CanRollback;
use DateTimeImmutable;
use Exception;

class MigrateHandler implements CommandHandler
{
    public function __construct(private readonly DataStoreFactory $dataStoreFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(MigrateCommand $c): void
    {
        $environment = $c->project->getEnvironmentMap()->get($c->envName);
        $target = $this->dataStoreFactory->createSession($environment->target);
        $migrationStore = $this->dataStoreFactory->createMigrationStore($c->project->getMigrationStore());
        $history = $this->dataStoreFactory->createHistory($environment);

        $deploymentId = $history->startDeployment(Command::MIGRATE, 'deployer',
            'app', $c->tag);

        foreach ($migrationStore->next() as $migration) {
            if (!($migration->script instanceof CanMigrate))
                continue;

            if (($status = $this->getStatus($history, $migration)) === null)
                continue;

            $target->beginTransaction();

            try {
                $migration->script->migrate($target);
                $history->addChange($this->createChange($deploymentId, $status, $migration));
                $target->commit();
            }
            catch (Exception $e) {
                ignoreException($target->rollBack(...));
                ignoreException(function () use ($history, $deploymentId, $e) {
                    $history->endDeployment($deploymentId, ['error' => $e->getMessage()]);
                });

                throw $e;
            }
        }

        $history->endDeployment($deploymentId);
    }

    /**
     * @param History $history
     * @param Migration $migration
     * @return Status|null change status or null if migration should be skipped
     * @throws Exception
     */
    private function getStatus(History $history, Migration $migration): Status|null
    {
        $change = $history->getLatestChange(new ChangeId($migration->id->value));

        /* doesn't exist yet, migrate it */
        if (!$change)
            return Status::MIGRATED;

        if ($change->isSql != $migration->script->isSql())
            ;//error

        $scriptChanged = $change->checksum->equals($migration->checksum);

        if ($change->migratePolicy == MigratePolicy::ONCE) {
            /* error: migrate once policy cannot change */
            if ($scriptChanged)
                ;// error

            return null;
        }

        if ($change->status == Status::ROLLBACKED) {
            /* error: rollbacked script cannot change */
            if ($scriptChanged)
                ; //error

            return null;
        }

        /* only migrate when script changes */
        if (!$scriptChanged && $change->migratePolicy == MigratePolicy::ONCHANGE)
            return null;

        /* remigrate: policy is ONCHANGE or ALWAYS */
        return Status::REMIGRATED;
    }

    /**
     * @param DeploymentId $deploymentId
     * @param Status $status
     * @param Migration $migration
     * @return Change
     * @throws Exception
     */
    private function createChange(DeploymentId $deploymentId, Status $status, Migration $migration): Change
    {
        return new Change(
            new ChangeId($migration->id->value),
            $deploymentId,
            $migration->location,
            $migration->script->getMigratePolicy(),
            $status,
            $migration->script->getAuthor(),
            $migration->checksum,
            $migration->script->getDescription(),
            $migration->script instanceof CanRollback,
            $migration->script->isSql(),
            $migration->script->getAuthoredAt(),
            new DateTimeImmutable(timezone: new \DateTimeZone('UTC'))
        );
    }
}