<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\MigratePolicy;
use Cinch\Database\Session;
use DateTimeInterface;
use Exception;

class SqlScript extends Script
{
    /**
     * @param string $migrateSql can be an empty string if there are no migrate commands
     * @param string $rollbackSql can be an empty string if there are no rollback commands
     * @param MigratePolicy $migratePolicy
     * @param Author $author
     * @param DateTimeInterface $authoredAt
     * @param Description $description
     */
    public function __construct(
        private readonly string $migrateSql,
        private readonly string $rollbackSql,
        MigratePolicy $migratePolicy,
        Author $author,
        DateTimeInterface $authoredAt,
        Description $description)
    {
        parent::__construct($migratePolicy, $author, $authoredAt, $description);
    }

    /**
     * @throws Exception
     */
    public function migrate(Session $session): void
    {
        if ($this->migrateSql)
            $session->executeStatement($this->migrateSql);
    }

    /**
     * @throws Exception
     */
    public function rollback(Session $session): void
    {
        if ($this->rollbackSql)
            $session->executeStatement($this->rollbackSql);
    }
}