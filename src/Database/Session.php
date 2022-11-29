<?php

namespace Cinch\Database;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Exception;
use RuntimeException;

/** Adds cinch-specific functionality to Doctrine Connection. */
class Session extends Connection
{
    private readonly Platform $platform;

    public function __construct(array $params, Driver $driver, ?Configuration $config = null, ?EventManager $eventManager = null)
    {
        $this->setPlatform($params);
        parent::__construct($params, $driver, $config, $eventManager);
    }

    public function getPlatform(): Platform
    {
        return $this->platform;
    }

    /**
     * @param string $value
     * @return string
     * @throws Exception
     */
    public function quoteString(string $value): string
    {
        /* Connection::quote should not be used, according to method docs. */
        return $this->getDatabasePlatform()->quoteStringLiteral($value);
    }

    /**
     * @param string $table should be schema qualified
     * @param string $idColumn sequence, auto_increment, identity column name
     * @param array $data assoc array of column_name => value.
     * @return int
     * @throws Exception
     */
    public function insertFetchId(string $table, string $idColumn, array $data): int
    {
        $columns = '';
        $values = [];
        $placeholders = '';
        $driver = $this->platform->getDriver();

        foreach ($data as $column => $value) {
            $comma = $columns ? ',' : '';
            $columns .= $comma . $column;
            $placeholders .= $comma . '?';
            $values[] = $value;
        }

        /* need auto-incremented value across databases. MySQL/MariaDB automatically return this in an
         * "OK Packet". The other databases support either RETURNING or OUTPUT clauses.
         */
        $insert = "insert into $table ($columns)";

        if ($driver == 'mssql')
            $insert .= " output inserted.$idColumn"; // must come before VALUES()

        $insert .= " values ($placeholders)";

        if ($driver == 'pgsql' || $driver == 'sqlite')
            $insert .= " returning $idColumn"; // must come after VALUES()

        if ($driver == 'mysql') {
            $this->executeStatement($insert, $values);
            return $this->lastInsertId(); // from ok packet
        }

        return $this->executeQuery($insert, $values)->fetchOne(); // from returning or output
    }

    private function setPlatform(array &$params): void
    {
        $platform = $params['cinch.platform'] ?? null;

        if (!($platform instanceof Platform))
            throw new RuntimeException("'cinch.platform' param is required and must implement " . Platform::class);

        $this->platform = $platform;
        unset($params['cinch.platform']);
    }
}