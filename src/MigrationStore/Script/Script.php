<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\MigratePolicy;
use Cinch\Common\Description;
use DateTimeInterface;

abstract class Script
{
    private readonly array $variables;

    public function __construct(
        private readonly MigratePolicy $migratePolicy,
        private readonly Author $author,
        private readonly DateTimeInterface $authoredAt,
        private readonly Description $description,
        private readonly bool $isSql = false)
    {
    }

    final public function isSql(): bool
    {
        return $this->isSql;
    }

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

    protected function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }
}