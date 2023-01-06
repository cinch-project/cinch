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
            'pgsql' => new Platform\PgSql,
            'mysql' => new Platform\MySql,
            'mssql' => new Platform\MsSql,
            'sqlite' => new Platform\Sqlite,
            default => throw new AssertException("unknown database platform '$dsn-˘driver'")
        };

        if ($driver == 'mssql')
            $driver = 'sqlsrv';

        $params = [
            'cinch.platform' => $platform,
            'driver' => "pdo_$driver",
            'wrapperClass' => Session::class, // allows us to extend Doctrine's built-in Connection
            'dbname' => $dsn->dbname,
            'user' => $dsn->user,
            'password' => $dsn->password,
            'host' => $dsn->host,
            'port' => $dsn->port,
            'driverOptions' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        ];

        /** @var Session $session */
        $session = DriverManager::getConnection($platform->addParams($dsn, $params));

        try {
            return $platform->initSession($session, $dsn);
        }
        catch (Exception $e) {
            $session->close();
            throw $e;
        }
    }
}