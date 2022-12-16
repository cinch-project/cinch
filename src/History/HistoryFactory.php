<?php

namespace Cinch\History;

use Cinch\Common\Environment;
use Cinch\Database\SessionFactory;
use Exception;
use Twig\Environment as Twig;

class HistoryFactory
{
    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly Twig $twig,
        private readonly SchemaVersion $schemaVersion,
        private readonly string $application)
    {
    }

    /**
     * @param Environment $environment
     * @return History
     * @throws Exception
     */
    public function create(Environment $environment): History
    {
        $session = $this->sessionFactory->create($environment->historyDsn);
        $schema = new Schema($session, $environment, $this->schemaVersion);
        return new History($schema, $this->twig, $this->application);
    }
}