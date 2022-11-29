<?php

namespace Cinch\Database\Platform;

use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Cinch\Database\Platform;
use Cinch\Database\Identifier;
use Cinch\Database\Session;
use Cinch\Database\UnsupportedVersionException;
use DateTimeInterface;
use Exception;
use PDO;

class MySqlPlatform implements Platform
{
    private readonly float $version;
    private readonly Session $session;
    private readonly string $platformName;
    private readonly string $dateTimeFormat;

    public function getName(): string
    {
        return $this->platformName;
    }

    public function getDriver(): string
    {
        return 'mysql';
    }

    public function getVersion(): float
    {
        return $this->version;
    }

    public function formatDateTime(DateTimeInterface $dateTime): string
    {
        return $dateTime->format($this->dateTimeFormat);
    }

    public function createIdentifier(string $value): Identifier
    {
        return new class($this->session, $value) extends Identifier {
            public function __construct(Session $session, string $value)
            {
                Assert::regex($value, '~^[\x{0001}-\x{ffff}]{1,64}(?<!\s)$~u', 'identifier');
                parent::__construct($value, $session->quoteString($value), $session->quoteIdentifier($value));
            }
        };
    }

    public function addParams(Dsn $dsn, array $params): array
    {
        $params['user'] = $dsn->getUser(default: 'root');
        $params['port'] = $dsn->getPort() ?? 3306;
        $params['driverOptions'][PDO::ATTR_EMULATE_PREPARES] = 1;
        $params['driverOptions'][PDO::ATTR_TIMEOUT] = $dsn->getConnectTimeout();

        $count = count($params['driverOptions']);

        if ($value = $dsn->getFile('sslca'))
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_CA] = $value;

        if ($value = $dsn->getFile('sslcert'))
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_CERT] = $value;

        if ($value = $dsn->getFile('sslkey'))
            $params['driverOptions'][PDO::MYSQL_ATTR_SSL_KEY] = $value;

        $params['driverOptions'][PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = count($params['driverOptions']) != $count;

        return $params;
    }

    public function initSession(Session $session, Dsn $dsn): Session
    {
        $version = $session->getNativeConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
        [$version, $minVersion, $this->platformName] = $this->parseVersion($version);

        $format = self::DATETIME_FORMAT;
        if (version_compare($version, '8.0.19', '<'))
            $format = substr($format, 0, -1); // time zone support added in 8.0.19, remove 'P'

        $this->dateTimeFormat = $format;
        $this->version = (float) $version;

        if ($this->version < $minVersion)
            throw new UnsupportedVersionException($this->platformName, $version, $minVersion);

        $charset = $session->quoteString($dsn->getOption('charset', 'utf8mb4'));
        $session->executeStatement("
            set character set $charset;
            set session max_execution_time={$dsn->getTimeout()}; 
            set session time_zone = '+00:00';");

        return $this->session = $session;
    }

    public function lockSession(string $name, int $timeout): bool
    {
        $result = $this->session->executeQuery("select get_lock(?, ?)", [$name, max(0, $timeout)]);
        $acquired = $result->fetchOne();

        if ($acquired !== null)
            return $acquired;

        /* mysql manual: "NULL if an error occurred (such as running out of memory or the thread was killed" */
        throw new Exception("an error occurred while trying to obtain lock '$name'");
    }

    public function unlockSession(string $name): void
    {
        $this->session->executeQuery('select release_lock(?)', [$name]);
    }

    private function parseVersion(string $version): array
    {
        $version = strtolower($version);

        /* 5.5.5-Mariadb-10.0.8-xenial */
        if ($mariadb = str_contains($version, 'mariadb')) {
            if (str_starts_with($version, '5.5.5-'))
                $version = substr($version, 6); /* some distros (incorrectly) prefix with '5.5.5-' */

            if (str_starts_with($version, 'mariadb-'))
                $version = substr($version, 8);
        }

        $name = $mariadb ? 'mariadb' : 'mysql';

        if (!preg_match('~^(\d+\.\d+(?:\.\d+)?)~', $version, $match))
            throw new \RuntimeException("Unknown $name version: $version");

        return [$match[1], $mariadb ? 10.2 : 5.7, $name];
    }
}