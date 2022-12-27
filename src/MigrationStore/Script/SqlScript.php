<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\MigratePolicy;
use Cinch\Database\Session;
use DateTimeInterface;
use Exception;

class SqlScript extends Script
{
    /**
     * @param string $migrate migrate sql: can be an empty string, in which case nothing is sent to server
     * @param string $rollback rollback sql: can be an empty string, in which case nothing is sent to server
     * @param MigratePolicy $migratePolicy
     * @param Author $author
     * @param DateTimeInterface $authoredAt
     * @param Description $description
     * @param Labels $labels
     */
    public function __construct(
        private readonly string $migrate,
        private readonly string $rollback,
        MigratePolicy $migratePolicy,
        Author $author,
        DateTimeInterface $authoredAt,
        Description $description,
        Labels $labels)
    {
        parent::__construct($migratePolicy, $author, $authoredAt, $description, $labels);
    }

    /**
     * @throws Exception
     */
    public function migrate(Session $session): void
    {
        if ($this->migrate)
            $session->executeStatement($this->migrate);
    }

    /**
     * @throws Exception
     */
    public function rollback(Session $session): void
    {
        if ($this->rollback)
            $session->executeStatement($this->rollback);
    }
}