<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\CommitPolicy;
use Cinch\Common\Description;
use DateTimeInterface;
use Cinch\Database\Session;
use Exception;

class SqlCommitScript extends Script implements Committable
{
    public function __construct(
        private readonly string $commitSql,
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
}