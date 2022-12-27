<?php

namespace Cinch\Database;

use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Doctrine\DBAL\DriverManager;
use Exception;
use PDO;

class SessionFactory
{
    /**
     * @throws Exception
     */
    public function create(Dsn $dsn): Session
    {
        $platform = match ($driver = $dsn->getScheme()) {
            'pgsql' => new Platform\PgSql,
            'mysql' => new Platform\MySql,
            'mssql' => new Platform\MsSql,
            'sqlite' => new Platform\Sqlite,
            default => throw new AssertException("unknown database platform '$driver'")
        };

        if ($driver == 'mssql')
            $driver = 'sqlsrv';

        $params = [
            'cinch.platform' => $platform,
            'driver' => "pdo_$driver",
            'wrapperClass' => Session::class, // allows us to extend Doctrine's built-in Connection
            'dbname' => Assert::notEmpty(trim($dsn->getPath(), '/'), 'dsn dbname'),
            'password' => $dsn->getPassword(),
            'host' => $dsn->getHost() ?: '127.0.0.1',
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