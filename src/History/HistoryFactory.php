<?php

namespace Cinch\History;

use Cinch\Common\Environment;
use Cinch\Component\TemplateEngine\TemplateEngine;
use Cinch\Database\SessionFactory;
use Exception;

class HistoryFactory
{
    public function __construct(
        private readonly SessionFactory $sessionFactory,
        private readonly TemplateEngine $templateEngine,
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
        return new History($schema, $this->templateEngine, $this->application);
    }
}
