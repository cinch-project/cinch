<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\CommitPolicy;
use Cinch\Common\Description;
use DateTimeInterface;

abstract class Script
{
    private readonly array $variables;

    public function __construct(
        private readonly CommitPolicy $commitPolicy,
        private readonly Author $author,
        private readonly DateTimeInterface $authoredAt,
        private readonly Description $description)
    {
    }

    public function getVariables(): array
    {
        return $this->variables ?? [];
    }

    public function getCommitPolicy(): CommitPolicy
    {
        return $this->commitPolicy;
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