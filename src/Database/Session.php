<?php

namespace Cinch\Database;

use Cinch\Database\Platform\Platform;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Exception;
use PDOException;
use RuntimeException;

/** Adds cinch-specific functionality to Doctrine Connection. */
class Session extends Connection
{
    private const NO_ACTIVE_TRANSACTION = 'There is no active transaction';

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

    public function beginTransaction(): bool
    {
        if ($this->isTransactionActive())
            throw new RuntimeException("A transaction is already active: nested transactions not supported");
        return parent::beginTransaction();
    }

    public function commit(): bool
    {
        try {
            return parent::commit();
        }
        catch (PDOException $e) {
            if ($this->isNoActiveTransactionException($e)) {
                $this->clearTransactions();
                return true;
            }

            throw $e;
        }
    }

    public function rollBack(): bool
    {
        try {
            return parent::rollBack();
        }
        catch (PDOException $e) {
            if ($this->isNoActiveTransactionException($e)) {
                $this->clearTransactions();
                return true;
            }

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function lock(string $name, int $timeout): bool
    {
        return $this->platform->lockSession($this, $name, $timeout);
    }

    /**
     * @throws Exception
     */
    public function unlock(string $name): void
    {
        $this->platform->unlockSession($this, $name);
    }

    /** Testing.
     * @param string $value
     * @return string
     * @throws Exception
     */
    public function quoteString(string $value): string
    {
        /* Connection::quote should not be used, according to method docs. */
        return $this->getDatabasePlatform()->quoteStringLiteral($value);
    }

    /** Inserts a record and returns the last insert identifier.
     * @param string $table should be schema qualified and properly quoted
     * @param string $idColumn sequence, auto_increment, identity column name
     * @param array $data assoc array of column_name => value.
     * @return int
     * @throws Exception
     */
    public function insertReturningId(string $table, string $idColumn, array $data): int
    {
        $columns = '';
        $values = [];
        $placeholders = '';
        $platformName = $this->platform->getName();

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

        if ($platformName == 'mssql')
            $insert .= " output inserted.$idColumn"; // must come before VALUES()

        $insert .= " values ($placeholders)";

        if ($platformName == 'pgsql' || $platformName == 'sqlite')
            $insert .= " returning $idColumn"; // must come after VALUES()

        if ($platformName == 'mysql' || $platformName == 'mariadb') {
            $this->executeStatement($insert, $values);
            return $this->lastInsertId(); // from ok packet
        }

        return $this->executeQuery($insert, $values)->fetchOne(); // from returning or output
    }

    private function isNoActiveTransactionException(PDOException $e): bool
    {
        /* for platforms without transaction DDL, DDL statements auto-commit any open transaction. If doctrine still
         * indicates an active transaction and we get NO_ACTIVE_TRANSACTION, cinch ignores the failed commit.
         */
        return !$this->platform->supportsTransactionalDDL()
            && $this->isTransactionActive()
            && $e->getMessage() == self::NO_ACTIVE_TRANSACTION;
    }

    private function clearTransactions(): void
    {
        /* must set $this->transactionNestingLevel back to zero, but it's private. Only close() sets it back
         * to zero. close() also sets $this->_conn (protected) to null, so save it before calling close().
         */
        $conn = $this->_conn;
        parent::close();
        $this->_conn = $conn;
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