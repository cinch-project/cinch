<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Database\Session;
use Cinch\MigrationStore\Migration;
use Exception;
use RuntimeException;

class Deployment
{
    private DeploymentTag|null $tag = null;
    private Session|null $session;
    private Schema|null $schema;

    /**
     * @throws Exception
     */
    public function __construct(
        Schema $schema,
        DeploymentCommand $command,
        DeploymentTag $tag,
        Author $deployer,
        string $application)
    {
        if (!($schema->state() & Schema::OBJECTS))
            throw new RuntimeException("cinch history '{$schema->name()}' contains no objects");

        $this->tag = $tag;
        $this->schema = $schema;
        $this->session = $schema->session();

        $this->open($command, $deployer, $application);
    }

    /**
     * @throws Exception
     */
    public function addChange(ChangeStatus $status, Migration $migration): void
    {
        $this->session->insert($this->schema->table('change'), [
            'location' => $migration->location->value,
            'tag' => $this->tag->value,
            'migrate_policy' => $migration->script->getMigratePolicy()->value,
            'status' => $status->value,
            'author' => $migration->script->getAuthor()->value,
            'checksum' => $migration->checksum->value,
            'description' => $migration->script->getDescription()->value,
            'authored_at' => $this->session->getPlatform()->formatDateTime($migration->script->getAuthoredAt()),
            'deployed_at' => $this->session->getPlatform()->formatDateTime()
        ]);
    }

    /**
     * @throws Exception
     */
    public function close(DeploymentError|null $error = null): void
    {
        try {
            $this->session->update($this->schema->table('deployment'), [
                'error' => $error ? json_encode($error, JSON_UNESCAPED_SLASHES) : null,
                'ended_at' => $this->session->getPlatform()->formatDateTime()
            ], ['tag' => $this->tag->value]);
        }
        finally {
            ignoreException($this->schema->unlock(...));
            $this->clear();
        }
    }

    /**
     * @throws Exception
     */
    private function open(DeploymentCommand $command, Author $deployer, string $application): void
    {
        $this->schema->lock();

        try {
            $table = $this->schema->table('deployment');

            $this->session->insert($table, [
                'tag' => $this->tag->value,
                'deployer' => $deployer->value,
                'command' => $command->value,
                'application' => $application,
                'schema_version' => $this->schema->version()->version,
                'started_at' => $this->session->getPlatform()->formatDateTime()
            ]);
        }
        catch (Exception $e) {
            ignoreException($this->schema->unlock(...));
            $this->clear();
            throw $e;
        }
    }

    /** Once a deployment object is closed, it cannot be reused. */
    private function clear(): void
    {
        $this->schema = $this->session = $this->tag = null;
    }
}