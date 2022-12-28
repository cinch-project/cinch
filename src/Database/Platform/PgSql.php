<?php

namespace Cinch\Database\Platform;

use Cinch\Common\Dsn;
use Cinch\Database\Platform;
use Cinch\Database\Session;
use Cinch\Database\UnsupportedVersionException;
use Doctrine\DBAL\Exception;
use PDO;

class PgSql extends Platform
{
    public function addParams(Dsn $dsn, array $params): array
    {
        $params['user'] = $dsn->getUser(default: 'postgres');
        $params['port'] = $dsn->getPort() ?? 5432;
        $params['driverOptions'][PDO::ATTR_EMULATE_PREPARES] = 1;
        $params['driverOptions'][PDO::ATTR_TIMEOUT] = $dsn->getConnectTimeout();

        $count = count($params);

        if ($value = $dsn->getOption('sslmode'))
            $params['sslmode'] = $value;

        if ($value = $dsn->getFile('sslca'))
            $params['sslrootcert'] = $value;

        if ($value = $dsn->getFile('sslcert'))
            $params['sslcert'] = $value;

        if ($value = $dsn->getFile('sslkey'))
            $params['sslkey'] = $value;

        if (!isset($params['sslmode']))
            $params['sslmode'] = count($params) != $count ? 'verify-full' : 'prefer';

        return $params;
    }

    public function initSession(Session $session, Dsn $dsn): Session
    {
        $this->version = (float) $session->getNativeConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);

        if ($this->version < 12.0)
            throw new UnsupportedVersionException('PostgreSQL', $this->version, 12.0);

        $charset = $session->quoteString($dsn->getOption('charset', 'UTF8'));

        if ($searchPath = $dsn->getOption('search_path', '')) {
            $schemas = preg_split('~\s*,\s*~', $searchPath, flags: PREG_SPLIT_NO_EMPTY);
            $schemas = implode(',', array_map(fn($s) => $session->quoteIdentifier($s), $schemas));
            $searchPath = "set search_path to $schemas;";
        }

        $session->executeStatement("
            $searchPath
            set client_encoding to $charset;
            set statement_timeout={$dsn->getTimeout()}; 
            set time zone '+00:00';");

        return $session;
    }

    public function lockSession(Session $session, string $name, int $timeout): bool
    {
        $key = $this->computeKey($name);

        /* try to lock for $timeout milliseconds */
        $timeout = max(0, $timeout) * 1000;

        do {
            if ($this->tryLock($session, $key))
                return true;

            if ($timeout == 0)
                break;

            /* try every 250ms */
            $n = min(250, $timeout);
            $timeout -= $n;
        } while (nanosleep($n * 1e6));

        return false;
    }

    public function unlockSession(Session $session, string $name): void
    {
        $session->executeQuery('select pg_advisory_unlock(?)', [$this->computeKey($name)]);
    }

    /**
     * @throws Exception
     */
    private function tryLock(Session $session, int $key): bool
    {
        return $session->executeQuery("select pg_try_advisory_lock(?)", [$key])->fetchOne();
    }

    private function computeKey(string $name): int
    {
        return hexdec(hash('xxh32', $name));
    }
}