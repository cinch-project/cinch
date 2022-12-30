<?php

namespace Cinch\Database\Platform;

use Cinch\Database\DatabaseDsn;
use Cinch\Database\Platform;
use Cinch\Database\Session;
use Cinch\Database\UnsupportedVersionException;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use PDO;
use RuntimeException;

class MySql extends Platform
{
    private readonly string $dateTimeFormat;

    public function getName(): string
    {
        return $this->name; // mysql or mariadb
    }

    public function supportsTransactionalDDL(): bool
    {
        return false; /* like oracle, mysql does not support this */
    }

    public function formatDateTime(DateTimeInterface|null $dt = null): string
    {
        if (!$dt)
            $dt = new DateTime(timezone: new DateTimeZone('UTC'));
        return $dt->format($this->dateTimeFormat);
    }

    public function addParams(DatabaseDsn $dsn, array $params): array
    {
        $params['driverOptions'][PDO::ATTR_EMULATE_PREPARES] = 1;
        $params['driverOptions'][PDO::ATTR_TIMEOUT] = $dsn->connectTimeout;

        $count = count($params['driverOptions']);

        if ($dsn->sslca)
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_CA] = $dsn->sslca;

        if ($dsn->sslcert)
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_CERT] = $dsn->sslcert;

        if ($dsn->sslkey)
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_KEY] = $dsn->sslkey;

        $params['driverOptions'][PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = count($params['driverOptions']) != $count;

        return $params;
    }

    public function initSession(Session $session, DatabaseDsn $dsn): Session
    {
        $version = $session->getNativeConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
        [$version, $minVersion, $this->name] = $this->parseVersion($version);

        $format = self::DATETIME_FORMAT;
        if (version_compare($version, '8.0.19', '<'))
            $format = substr($format, 0, -1); // time zone offset support added in 8.0.19, remove 'P'

        $this->dateTimeFormat = $format;
        $this->version = (float) $version;

        if ($this->version < $minVersion)
            throw new UnsupportedVersionException($this->name, $this->version, $minVersion);

        $charset = $session->quoteString($dsn->charset);
        $session->executeStatement("
            set autocommit = 1;
            set character set $charset;
            set session max_execution_time=$dsn->timeout; 
            set session time_zone = '+00:00';");

        return $session;
    }

    public function lockSession(Session $session, string $name, int $timeout): bool
    {
        $result = $session->executeQuery("select get_lock(?, ?)", [$name, max(0, $timeout)]);
        $acquired = $result->fetchOne();

        if ($acquired !== null)
            return $acquired;

        /* mysql manual: "NULL if an error occurred (such as running out of memory or the thread was killed" */
        throw new Exception("an error occurred while trying to obtain lock '$name'");
    }

    public function unlockSession(Session $session, string $name): void
    {
        $session->executeQuery('select release_lock(?)', [$name]);
    }

    private function parseVersion(string $version): array
    {
        $version = strtolower($version);

        /* Mariadb-10.0.8-xenial */
        if ($mariadb = str_contains($version, 'mariadb')) {
            if (str_starts_with($version, '5.5.5-'))
                $version = substr($version, 6); /* some distros (incorrectly) prefix with '5.5.5-' */

            if (str_starts_with($version, 'mariadb-'))
                $version = substr($version, 8);
        }

        $name = $mariadb ? 'mariadb' : 'mysql';

        if (!preg_match('~^(\d+\.\d+(?:\.\d+)?)~', $version, $match))
            throw new RuntimeException("Unknown $name version: $version");

        return [$match[1], $mariadb ? 10.2 : 5.7, $name];
    }
}