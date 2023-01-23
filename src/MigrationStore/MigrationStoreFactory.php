<?php

namespace Cinch\MigrationStore;

use Cinch\MigrationStore\Script\ScriptLoader;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Twig\Environment as Twig;

class MigrationStoreFactory
{
    public function __construct(
        private readonly ScriptLoader $scriptLoader,
        private readonly Twig $twig,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly string $resourceDir,
        private readonly string $userAgent)
    {
    }

    /**
     * @throws Exception
     */
    public function create(StoreDsn $dsn): MigrationStore
    {
        $adapter = match ($dsn->adapter) {
            'fs' => Adapter\Local::fromDsn($dsn, $this->projectDir),
            'github' => Adapter\GitHub::fromDsn($dsn, $this->userAgent),
            'gitlab' => Adapter\GitLab::fromDsn($dsn, $this->userAgent),
            'azure' => Adapter\Azure::fromDsn($dsn, $this->userAgent),
            default => throw new RuntimeException("unsupported migration store adapter '$dsn->adapter'")
        };

        return new MigrationStore($adapter, $this->scriptLoader, $this->twig, $this->logger, $this->resourceDir);
    }
}