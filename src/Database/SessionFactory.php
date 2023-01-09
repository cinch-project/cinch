<?php

namespace Cinch\Database;

use Cinch\Component\Assert\AssertException;
use Doctrine\DBAL\DriverManager;
use Exception;
use PDO;

class SessionFactory
{
    /**
     * @throws Exception
     */
    public function create(DatabaseDsn $dsn): Session
    {
        $platform = match ($driver = $dsn->driver) {
            'pgsql' => new Platform\PgSql($dsn),
            'mysql' => new Platform\MySql($dsn),
            'mssql' => new Platform\MsSql($dsn),
            'sqlite' => new Platform\Sqlite($dsn),
            default => throw new AssertException("unknown database platform '$dsn'")
        };

        if ($driver == 'mssql')
            $driver = 'sqlsrv';

        /** @var Session $session */
        $session = DriverManager::getConnection($platform->addParams([
            'cinch.platform' => $platform,
            'driver' => "pdo_$driver",
            'wrapperClass' => Session::class, // extend Doctrine's built-in Connection
            'dbname' => $dsn->dbname,
            'user' => $dsn->user,
            'password' => $dsn->password,
            'host' => $dsn->host,
            'port' => $dsn->port,
            'driverOptions' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        ]));

        return $session;
    }
}