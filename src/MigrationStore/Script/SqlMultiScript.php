<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\Author;
use Cinch\Common\MigratePolicy;
use Cinch\Common\Description;
use DateTimeInterface;
use Cinch\Database\Session;
use Exception;

class SqlMultiScript extends Script implements CanMigrate, CanRollback
{
    public function __construct(
        private readonly string $migrateSql,
        private readonly string $rollbackSql,
        MigratePolicy $migratePolicy,
        Author $author,
        DateTimeInterface $authoredAt,
        Description $description)
    {
        parent::__construct($migratePolicy, $author, $authoredAt, $description, isSql: true);
    }

    /**
     * @throws Exception
     */
    public function migrate(Session $session): void
    {
        $session->executeStatement($this->migrateSql);
    }

    /**
     * @throws Exception
     */
    public function rollback(Session $session): void
    {
        $session->executeStatement($this->rollbackSql);
    }
}