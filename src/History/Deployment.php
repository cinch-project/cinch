<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\StorePath;
use Cinch\Database\Session;
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
        string $application,
        private readonly bool $isSingleTransactionMode)
    {
        if (!($schema->state() & Schema::OBJECTS))
            throw new RuntimeException("cinch history '{$schema->name()}' contains no objects");

        $this->tag = $tag;
        $this->schema = $schema;
        $this->session = $schema->session();

        $this->open($command, $deployer, $application);
    }

    public function getTag(): DeploymentTag|null
    {
        return $this->tag;
    }

    /**
     * @throws Exception
     */
    private function open(DeploymentCommand $command, Author $deployer, string $application): void
    {
        $this->schema->lock();

        try {
            $this->session->insert($this->schema->table('deployment'), [
                'tag' => $this->tag->value,
                'deployer' => $deployer->value,
                'command' => $command->value,
                'application' => $application,
                'schema_version' => $this->schema->version()->version,
                'started_at' => $this->session->getPlatform()->formatDateTime()
            ]);
        }
        catch (Exception $e) {
            $this->clear();
            throw $e;
        }

        if ($this->isSingleTransactionMode) {
            try {
                $this->session->beginTransaction();
            }
            catch (Exception $e) {
                $this->clear();
                throw $e;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function addChange(Change $change): void
    {
        /* if NOT isSingleTransactionMode, a single insert handled by autocommit */
        $formatDateTime = $this->session->getPlatform()->formatDateTime(...);
        $this->session->insert($this->schema->table('change'), $change->snapshot($formatDateTime));
    }

    /**
     * @throws Exception
     * @internal
     */
    public function removeChange(StorePath $path): void
    {
        $this->session->delete($this->schema->table('change'), [
            'path' => $path->value,
            'tag' => $this->tag->value
        ]);
    }

    /**
     * @throws Exception
     */
    public function close(DeploymentError|null $error = null): void
    {
        if ($this->isSingleTransactionMode) {
            try {
                if ($error)
                    $this->session->rollBack();
                else
                    $this->session->commit();
            }
            catch (Exception $e) {
                if ($error) {
                    $extraMessage = get_class($e) . ' - ' . $e->getMessage();
                    $error = new DeploymentError(
                        "$error->message - rollback failed with $extraMessage",
                        $error->exception,
                        $error->file,
                        $error->line,
                        $error->trace
                    );
                }
                else {
                    $error = DeploymentError::fromException($e);
                }
            }
        }

        try {
            $this->session->update($this->schema->table('deployment'), [
                'error' => $error?->message,
                'error_details' => $error ? json_encode($error, JSON_UNESCAPED_SLASHES) : null,
                'ended_at' => $this->session->getPlatform()->formatDateTime()
            ], ['tag' => $this->tag->value]);
        }
        finally {
            $this->clear();
        }
    }

    private function clear(): void
    {
        silent_call($this->schema->unlock(...));
        $this->schema = $this->session = $this->tag = null;
    }
}