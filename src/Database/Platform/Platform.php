<?php

namespace Cinch\Database\Platform;

use Cinch\Common\Dsn;
use Cinch\Database\Session;
use Cinch\Database\UnsupportedVersionException;
use DateTimeInterface;
use Exception;

interface Platform
{
    const DATETIME_FORMAT = 'Y-m-d H:i:s.uP';

    /** Gets the platform name.
     * @return string
     */
    public function getName(): string;

    public function supportsTransactionalDDL(): bool;

    /** Formats a platform-aware datetime.
     * @param DateTimeInterface|null $dt
     * @return string
     */
    public function formatDateTime(DateTimeInterface|null $dt = null): string;

    /** Gets the platform version: major.minor only.
     * @return float
     */
    public function getVersion(): float;

    /** Asserts a platform identifier: database, schema, table, column, etc.
     * @param string $value
     * @return string
     */
    public function assertIdentifier(string $value): string;

    /** Adds platform-specific connection parameters.
     * @param Dsn $dsn
     * @param array $params current parameters
     * @return array updated version of $params
     */
    public function addParams(Dsn $dsn, array $params): array;

    /** Initializes a session. All platforms should perform version checking.
     * @param Session $session
     * @param Dsn $dsn
     * @throws Exception|UnsupportedVersionException
     */
    public function initSession(Session $session, Dsn $dsn): Session;

    /** Locks a session. This is an application (advisory) lock, not a table lock.
     * @param Session $session
     * @param string $name a good choice is the schema name history resides in
     * @param int $timeout seconds
     * @return bool true if lock was acquired and false if not
     * @throws Exception error occurred
     */
    public function lockSession(Session $session, string $name, int $timeout): bool;

    /** Unlocks a session.
     * @param Session $session
     * @param string $name
     * @throws Exception
     */
    public function unlockSession(Session $session, string $name): void;
}