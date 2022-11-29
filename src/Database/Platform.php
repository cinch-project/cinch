<?php

namespace Cinch\Database;

use Cinch\Common\Dsn;
use Exception;

interface Platform
{
    const DATETIME_FORMAT = 'Y-m-d H:i:s.uP';

    /** Gets the platform name.
     * @return string
     */
    public function getName(): string;

    /** Gets the driver name.
     * @return string
     */
    public function getDriver(): string;

    public function formatDateTime(\DateTimeInterface $dateTime): string;

    /** Gets the platform version: major.minor only.
     * @return float
     */
    public function getVersion(): float;

    /** Creates a platform identifier: database, schema, table, column, etc.
     * @param string $value
     * @return Identifier
     */
    public function createIdentifier(string $value): Identifier;

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
     * @param string $name
     * @param int $timeout seconds
     * @return bool true if lock was acquired and false if not
     * @throws Exception error occurred
     */
    public function lockSession(string $name, int $timeout): bool;

    /** Unlocks a session.
     * @param string $name
     * @throws Exception
     */
    public function unlockSession(string $name): void;
}