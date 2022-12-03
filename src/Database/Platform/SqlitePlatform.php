<?php

namespace Cinch\Database\Platform;

use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Cinch\Database\Platform;
use Cinch\Database\Session;
use Cinch\Database\UnsupportedVersionException;
use Cinch\LastErrorException;
use Exception;
use PDO;

class SqlitePlatform implements Platform
{
    use PlatformHelpers;

    /** use a file lock to lock session. concurrent deployments using a sqlite history are only
     * possible on the same machine. Also, there is no good mechanism within sqlite itself.
     * @var resource|null
     */
    private $lockStream = null;
    private readonly string $lockPath; // sqlite db path from dsn

    public function assertIdentifier(string $value): string
    {
        return Assert::regex($value, '~^[\x{0001}-\x{ffff}]+$~u', 'identifier');
    }

    public function addParams(Dsn $dsn, array $params): array
    {
        $params['path'] = $dsn->getPath();
        $params['driverOptions'][PDO::ATTR_EMULATE_PREPARES] = 1;
        return $params;
    }

    public function initSession(Session $session, Dsn $dsn): Session
    {
        $this->lockPath = $dsn->getPath();

        $this->version = (float) $session->getNativeConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
        if ($this->version < 3.0)
            throw new UnsupportedVersionException('SQLite', $this->version, 3.0);

        $session->executeStatement('pragma foreign_keys = on');
        return $session;
    }

    public function lockSession(Session $session, string $name, int $timeout): bool
    {
        if ($this->lockStream) {
            // TODO: $this->logger->debug()
            fprintf(STDERR, "warning: attempt to lock $name while it is already locked\n");
            return true;
        }

        if (($fp = @fopen("$this->lockPath.$name.lock", 'w+')) === false)
            throw new LastErrorException();

        /* try to lock for $timeout milliseconds */
        $timeout = max(0, $timeout) * 1000;

        do {
            $tryAgain = 0;

            if (@flock($fp, LOCK_EX | LOCK_NB, $tryAgain))
                return !!($this->lockStream = $fp);

            /* error occurred */
            if (!$tryAgain)
                $this->throwLockError($fp);

            if ($timeout == 0)
                break;

            $n = min(100, $timeout);
            $timeout -= $n;
        } while (nanosleep($n * 1e6));

        fclose($fp);
        return false; // timeout
    }

    public function unlockSession(Session $session, string $name): void
    {
        if (!$this->lockStream)
            return;

        $path = stream_get_meta_data($this->lockStream)['uri'];

        if (str_ends_with($path, "$name.lock")) {
            fclose($this->lockStream); // releases any locks
            $this->lockStream = null;
        }
        else {
            // TODO: $this->logger->debug()
            fprintf(STDERR, "locked file is '$path', given wrong name '$name'\n");
        }
    }

    /**
     * @param resource $fp
     * @throws Exception
     */
    private function throwLockError($fp): void
    {
        $e = new LastErrorException();
        fclose($fp);
        throw $e;
    }
}