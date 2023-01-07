<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\StorePath;
use Cinch\Database\Session;
use Exception;
use RuntimeException;

class Deployment
{
    private DeploymentTag|null $tag;
    private Session|null $session;
    private Schema|null $schema;

    /**
     * @throws Exception
     */
    public function __construct(
        Schema $schema,
        private readonly DeploymentCommand $command,
        DeploymentTag $tag,
        private readonly Author $deployer,
        private readonly string $application,
        private readonly bool $isDryRun,
        private readonly bool $isSingleTransactionMode)
    {
        if (!($schema->state() & Schema::OBJECTS))
            throw new RuntimeException("cinch history '{$schema->name()}' contains no objects");

        $this->tag = $tag;
        $this->schema = $schema;
        $this->session = $schema->session();
    }

    public function getTag(): DeploymentTag|null
    {
        return $this->tag;
    }

    /**
     * @return DeploymentCommand
     */
    public function getCommand(): DeploymentCommand
    {
        return $this->command;
    }

    public function isDryRun(): bool
    {
        return $this->isDryRun;
    }

    /**
     * @return bool
     */
    public function isSingleTransactionMode(): bool
    {
        return $this->isSingleTransactionMode;
    }

    /**
     * @throws Exception
     */
    public function open(): void
    {
        $this->schema->lock();

        try {
            if (!$this->isDryRun)
                $this->session->insert($this->schema->table('deployment'), [
                    'tag' => $this->tag->value,
                    'deployer' => $this->deployer->value,
                    'command' => $this->command->value,
                    'application' => $this->application,
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
                silent_call($this->session->delete(...), ['tag' => $this->tag->value]);
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
        if ($this->isDryRun)
            return;

        $formatDateTime = $this->session->getPlatform()->formatDateTime(...);
        $this->session->insert($this->schema->table('change'), $change->snapshot($formatDateTime));
    }

    /**
     * @throws Exception
     * @internal
     */
    public function removeChange(StorePath $path): void
    {
        if (!$this->isDryRun)
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
            if (!$this->isDryRun)
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