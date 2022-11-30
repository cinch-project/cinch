<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Dsn;
use Cinch\MigrationStore\Adapter\AzureAdapter;
use Cinch\MigrationStore\Adapter\GitHubAdapter;
use Cinch\MigrationStore\Adapter\GitLabAdapter;
use Cinch\MigrationStore\Adapter\LocalAdapter;
use Cinch\MigrationStore\Script\ScriptLoader;
use Exception;
use RuntimeException;

class MigrationStoreFactory
{
    public function __construct(
        private readonly ScriptLoader $scriptLoader,
        private readonly MigrationFactory $migrationFactory,
        private readonly string $projectDir,
        private readonly string $resourceDir,
        private readonly string $userAgent)
    {
    }

    /**
     * @throws Exception
     */
    public function create(Dsn $dsn): MigrationStore
    {
        $adapter = match ($dsn->getScheme()) {
            'file' => LocalAdapter::fromDsn($dsn, $this->projectDir),
            'github' => GitHubAdapter::fromDsn($dsn, $this->userAgent),
            'gitlab' => GitLabAdapter::fromDsn($dsn, $this->userAgent),
            'azure' => AzureAdapter::fromDsn($dsn, $this->userAgent),
            default => throw new RuntimeException("unsupported migration store adapter '{$dsn->getScheme()}'")
        };

        return new MigrationStore($adapter, $this->scriptLoader, $this->resourceDir, $this->migrationFactory);
    }
}