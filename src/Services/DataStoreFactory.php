<?php

namespace Cinch\Services;

use Cinch\MigrationStore\MigrationStore;
use Cinch\MigrationStore\MigrationStoreFactory;
use Cinch\Common\Dsn;
use Cinch\Database\Session;
use Cinch\Database\SessionFactory;
use Cinch\History\History;
use Cinch\History\SchemaVersion;
use Exception;
use Twig\Environment as TwigEnvironment;
use Cinch\Project\Environment;

class DataStoreFactory
{
    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly MigrationStoreFactory $storeFactory,
        private readonly TwigEnvironment $twig,
        private readonly string $application,
        private readonly SchemaVersion $schemaVersion)
    {
    }

    /**
     * @throws Exception
     */
    public function createSession(Dsn $dsn): Session
    {
        return $this->sessionFactory->create($dsn);
    }

    /**
     * @param Dsn $dsn
     * @return MigrationStore
     * @throws Exception
     */
    public function createMigrationStore(Dsn $dsn): MigrationStore
    {
        return $this->storeFactory->create($dsn);
    }

    /**
     * @param Environment $environment
     * @return History
     * @throws Exception
     */
    public function createHistory(Environment $environment): History
    {
        return new History(
            $this->createSession($environment->history),
            $this->schemaVersion,
            $this->twig,
            $this->application,
            $environment
        );
    }
}