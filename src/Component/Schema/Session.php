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

class Session
{
    private readonly string $platformName;

    /**
     * @param Connection $connection
     * @throws Exception
     */
    public function __construct(private readonly Connection $connection)
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof SqlitePlatform)
            $this->platformName = 'sqlite';
        else if ($platform instanceof PostgreSQLPlatform)
            $this->platformName = 'pgsql';
        else if ($platform instanceof MariaDBPlatform)
            $this->platformName = 'mariadb';
        else if ($platform instanceof MySQLPlatform)
            $this->platformName = 'mysql';
        else if ($platform instanceof SQLServerPlatform)
            $this->platformName = 'sqlsrv';
        else
            throw new RuntimeException("unsupported database platform: " . get_class($platform));
    }

    public function getPlatformName(): string
    {
        return $this->platformName;
    }

    public function quoteIdentifier(string $id): string
    {
        return $this->connection->quoteIdentifier($id);
    }

    /** Quotes a string literal.
     * @param string $value
     * @return string
     * @throws Exception
     */
    public function quoteString(string $value): string
    {
        return $this->connection->getDatabasePlatform()->quoteStringLiteral($value);
    }

    /**
     * @param string $sql
     * @param array $params
     * @return Result
     * @throws Exception
     */
    public function query(string $sql, array $params = []): Result
    {
        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * @param string $sql
     * @param array $params
     * @return int
     * @throws Exception
     */
    public function statement(string $sql, array $params = []): int
    {
        return $this->connection->executeStatement($sql, $params);
    }

    /**
     * @param string $table
     * @param array $data
     * @return int
     * @throws Exception
     */
    public function insert(string $table, array $data): int
    {
        return $this->connection->insert($table, $data);
    }

    /**
     * @param string $table
     * @param array $data
     * @param array $criteria
     * @return int
     * @throws Exception
     */
    public function update(string $table, array $data, array $criteria): int
    {
        return $this->connection->update($table, $data, $criteria);
    }

    /**
     * @param string $table
     * @param array $criteria
     * @return int
     * @throws Exception
     */
    public function delete(string $table, array $criteria): int
    {
        return $this->connection->delete($table, $criteria);
    }


    // createTableFrom('target', 'source')
    // createTable()->execute()
    // renameTable()->execute()
    // dropTable->execute()
    // alterTable()->column()->execute()

    public function schemaExists(string $schemaName): bool
    {
        return false;
    }
}

