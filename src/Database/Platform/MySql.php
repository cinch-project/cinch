<?php

namespace Cinch\Database\Platform;

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
    private readonly bool $supportsCheckConstraints;

    public function getName(): string
    {
        return $this->name; // mysql or mariadb
    }

    public function supportsTransactionalDDL(): bool
    {
        return false; /* like oracle, mysql does not support this */
    }

    public function supportsCheckConstraints(): bool
    {
        return $this->supportsCheckConstraints;
    }

    public function formatDateTime(DateTimeInterface|null $dt = null): string
    {
        if (!$dt)
            $dt = new DateTime(timezone: new DateTimeZone('UTC'));
        return $dt->format($this->dateTimeFormat);
    }

    public function addParams(array $params): array
    {
        $params['driverOptions'][PDO::ATTR_EMULATE_PREPARES] = 1;
        $params['driverOptions'][PDO::ATTR_TIMEOUT] = $this->dsn->connectTimeout;

        $count = count($params['driverOptions']);

        if ($this->dsn->sslca)
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_CA] = $this->dsn->sslca;

        if ($this->dsn->sslcert)
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_CERT] = $this->dsn->sslcert;

        if ($this->dsn->sslkey)
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_KEY] = $this->dsn->sslkey;

        $params['driverOptions'][PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = count($params['driverOptions']) != $count;

        return $params;
    }

    public function initSession(Session $session): Session
    {
        $version = $session->getNativeConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
        [$this->version, $minVersion] = $this->parseVersion($version);

        if (version_compare($this->version, $minVersion, '<'))
            throw new UnsupportedVersionException($this->name, $this->version, $minVersion);

        $this->supportsCheckConstraints = $this->name == 'mariadb' ||
            version_compare($this->version, '8.0.16', '>=');

        $format = self::DATETIME_FORMAT;

        /* before mysql 8.0.19, time zone offsets were not supported. mariadb still has no support */
        if ($this->name == 'mariadb' || version_compare($this->version, '8.0.19', '<'))
            $format = substr($format, 0, -1); // remove PHP's 'P' specifier

        $this->dateTimeFormat = $format;

        $charset = $session->quoteString($this->dsn->charset);
        $session->executeStatement("
            set autocommit = 1;
            set character set $charset;
            set session max_execution_time={$this->dsn->timeout}; 
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

        $this->name = $mariadb ? 'mariadb' : 'mysql';
        return [$this->parseServerVersion($version), $mariadb ? '10.3.0' : '5.7.0'];
    }
}
