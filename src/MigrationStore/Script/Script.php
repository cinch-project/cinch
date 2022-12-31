<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\MigratePolicy;
use Cinch\Database\Session;
use DateTimeInterface;
use Exception;

abstract class Script
{
    private readonly array $variables;

    public function __construct(
        private readonly MigratePolicy $migratePolicy,
        private readonly Author $author,
        private readonly DateTimeInterface $authoredAt,
        private readonly Description $description,
        private readonly Labels $labels)
    {
    }

    /**
     * @throws Exception
     */
    public abstract function migrate(Session $target): void;

    /**
     * @throws Exception
     */
    public abstract function rollback(Session $target): void;

    public function getVariables(): array
    {
        return $this->variables ?? [];
    }

    public function getMigratePolicy(): MigratePolicy
    {
        return $this->migratePolicy;
    }

    public function getAuthor(): Author
    {
        return $this->author;
    }

    public function getAuthoredAt(): DateTimeInterface
    {
        return $this->authoredAt;
    }

    public function getDescription(): Description
    {
        return $this->description;
    }

    /**
     * @return Labels
     */
    public function getLabels(): Labels
    {
        return $this->labels;
    }

    /**
     * @param array $variables
     * @return void
     * @internal
     */
    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }
}