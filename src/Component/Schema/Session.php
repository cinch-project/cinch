<?php

namespace Cinch\Component\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Result;
use Exception;
use RuntimeException;

interface Session
{
    public function getPlatformName(): string;

    public function quoteIdentifier(string $id): string;

    /** Quotes a string literal.
     * @param string $value
     * @return string
     * @throws Exception
     */
    public function quoteString(string $value): string;


    public function query(string $sql, array $params = []): QueryResult;
    /**
     * @param string $sql
     * @param array $params
     * @return int
     * @throws Exception
     */
    public function statement(string $sql, array $params = []): int;

    public function insert(string $table, array $data): int;

    public function update(string $table, array $data, array $criteria): int;

    public function delete(string $table, array $criteria): int;


    // createTableFrom('target', 'source')
    // createTable()->execute()
    // renameTable()->execute()
    // dropTable->execute()
    // alterTable()->column()->execute()

    public function schemaExists(string $schemaName): bool;
}
