<?php

namespace Cinch\Command;

use Cinch\History\Change;
use Cinch\History\Command;
use Cinch\History\DeploymentId;
use Cinch\History\Status;
use Cinch\MigrationStore\Migration;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

class RollbackHandler implements CommandHandler
{
    public function __construct(private readonly DataStoreFactory $dataStoreFactory)
    {
    }

    /**
     * @throws Exception
     */
    public function handle(MigrateCommand $c): void
    {
        // tag, count, date, script...

        $environment = $c->project->getEnvironmentMap()->get($c->envName);
        $target = $this->dataStoreFactory->createSession($environment->target);
        $migrationStore = $this->dataStoreFactory->createMigrationStore($c->project->getMigrationStore());
        $history = $this->dataStoreFactory->createHistory($environment);

        $deploymentId = $history->startDeployment(Command::ROLLBACK, $c->deployer, $c->tag);

        // select * from change where deployed_at > (select ended_at from deployment where tag = tag)
        //     and status <> 'rollbacked'
        // migrated
        // rollbacked
        // remigrated
        // rollbacked
        // select * from change where status <> 'rollbacked' order by deployed_at desc limit 5;
        // select * from change where deployed_at > datetime and status <> 'rollbacked' order by deployed_at desc;

        // change_id, directory, script:
        // getChangesSinceDateTime(dt), getChangesSinceTag(tag), getLatestChanges(count)
        // foreach (changes as $change)
        //    $migration = $migrationStore->getMigration($change->location)

        /** @var Change[] $changes */
        $changes = [null];

        foreach ($changes as $change) {
            $migration = $migrationStore->getMigration($change->location);

            if (!$change->checksum->equals($migration->checksum))
                throw new Exception("rollback '$change->location' failed: script changed since last migrated");

            $target->beginTransaction();

            try {
                $migration->script->rollback($target);
                $history->addChange($this->createChange($deploymentId, Status::ROLLBACKED, $migration));
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
     * @param DeploymentId $deploymentId
     * @param Status $status
     * @param Migration $migration
     * @return Change
     * @throws Exception
     */
    private function createChange(DeploymentId $deploymentId, Status $status, Migration $migration): Change
    {
        return new Change(
            $migration->location,
            $deploymentId,
            $migration->script->getMigratePolicy(),
            $status,
            $migration->script->getAuthor(),
            $migration->checksum,
            $migration->script->getDescription(),
            $migration->script->getAuthoredAt(),
            new DateTimeImmutable(timezone: new DateTimeZone('UTC'))
        );
    }
}