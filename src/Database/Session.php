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

    /** Quotes a string literal.
     * @param string $value
     * @return string
     * @throws Exception
     */
    public function quoteString(string $value): string
    {
        /* Connection::quote should not be used, according to method docs. */
        return $this->getDatabasePlatform()->quoteStringLiteral($value);
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