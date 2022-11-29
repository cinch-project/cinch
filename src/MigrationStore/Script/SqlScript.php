<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\CommitPolicy;
use Cinch\Common\Description;
use DateTimeInterface;
use Cinch\Database\Session;
use Exception;

class SqlScript extends Script implements Committable, Revertable
{
    public function __construct(
        private readonly string $commitSql,
        private readonly string $revertSql,
        CommitPolicy $commitPolicy,
        Author $author,
        DateTimeInterface $authoredAt,
        Description $description)
    {
        parent::__construct($commitPolicy, $author, $authoredAt, $description);
    }

    /**
     * @throws Exception
     */
    public function commit(Session $session): void
    {
        $session->executeStatement($this->commitSql);
    }

    /**
     * @throws Exception
     */
    public function revert(Session $session): void
    {
        $session->executeStatement($this->revertSql);
    }
}