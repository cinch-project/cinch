<?php

namespace Cinch\Database;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;

abstract class Platform
{
    const DATETIME_FORMAT = 'Y-m-d H:i:s.uP';

    protected float $version;
    protected string $name;

    /** Gets the platform name.
     * @return string
     */
    public function getName(): string
    {
        if (!isset($this->name))
            $this->name = strtolower(classname(static::class));
        return $this->name;
    }

    public function supportsTransactionalDDL(): bool
    {
        return true; /* default, since most have support */
    }

    /** Formats a platform-aware datetime.
     * @param DateTimeInterface|null $dt
     * @return string
     * @throws Exception
     */
    public function formatDateTime(DateTimeInterface|null $dt = null): string
    {
        if (!$dt)
            $dt = new DateTime(timezone: new DateTimeZone('UTC'));
        return $dt->format(self::DATETIME_FORMAT);
    }

    /** Gets the platform version: major.minor only.
     * @return float
     */
    public function getVersion(): float
    {
        return $this->version;
    }

    /** Adds platform-specific connection parameters just before connecting.
     * @param DatabaseDsn $dsn
     * @param array $params current parameters
     * @return array updated version of $params
     */
    public abstract function addParams(DatabaseDsn $dsn, array $params): array;

    /** Initializes a session just after connecting. All platforms should perform version checking.
     * @param Session $session
     * @param DatabaseDsn $dsn
     * @throws Exception|UnsupportedVersionException
     */
    public abstract function initSession(Session $session, DatabaseDsn $dsn): Session;

    /** Locks a session. This is an application (advisory) lock, not a table lock.
     * @param Session $session
     * @param string $name a good choice is the schema name history resides in
     * @param int $timeout seconds
     * @return bool true if lock was acquired and false if not
     * @throws Exception error occurred
     */
    public abstract function lockSession(Session $session, string $name, int $timeout): bool;

    /** Unlocks a session.
     * @param Session $session
     * @param string $name
     * @throws Exception
     */
    public abstract function unlockSession(Session $session, string $name): void;
}