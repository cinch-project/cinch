<?php

namespace Cinch\Database;

use Cinch\Common\Dsn;
use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;

class DatabaseDsn extends Dsn
{
    /** Database name or path to sqlite database file */
    public readonly string|null $dbname;
    public readonly string|null $user;
    public readonly string|null $password;
    public readonly string|null $host;
    public readonly int|null $port;
    public readonly string|null $charset;
    /** postgres search path */
    public readonly string|null $searchPath;
    /** postgres sslmode */
    public readonly string|null $sslmode;
    protected array $hidden = ['password'];

    protected function setParameters(array $params): void
    {
        parent::setParameters($params);

        [$user, $port, $charset, $host] = $this->getDefaults();
        $this->dbname = Assert::thatKey($params, 'dbname', 'dbname')->notEmpty()->value();
        $this->user = $params['user'] ?? $user;
        $this->password = $params['password'] ?? null;
        $this->host = $params['host'] ?? $host;
        $this->port = $params['port'] ?? $port;
        $this->charset = $params['charset'] ?? $charset;

        if ($this->driver == 'pgsql') {
            $this->sslmode = $params['sslmode'] ?? null;
            $this->searchPath = $params['search_path'] ?? null;
        }
        else {
            $this->sslmode = $this->searchPath = null;
        }
    }

    protected function getDefaults(): array
    {
        $localhost = '127.0.0.1';
        return match ($this->driver) {
            'mssql' => ['sa', 1443, null, $localhost],
            'pgsql' => ['postgres', 5432, 'UTF8', $localhost],
            'mysql' => ['root', 3306, 'utf8mb4', $localhost],
            'sqlite' => [null, null, null, null],
            default => throw new AssertException("unknown database driver '$this->driver'")
        };
    }
}