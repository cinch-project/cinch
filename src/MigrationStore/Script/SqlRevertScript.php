<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\CommitPolicy;
use Cinch\Common\Description;
use DateTimeInterface;
use Cinch\Database\Session;
use Exception;

class SqlRevertScript extends Script implements Revertable
{
    public function __construct(
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
    public function revert(Session $session): void
    {
        $session->executeStatement($this->revertSql);
    }
}