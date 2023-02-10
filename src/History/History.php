<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\MigratePolicy;
use Cinch\Component\Assert\Assert;
use Cinch\Component\TemplateEngine\TemplateEngine;
use Cinch\Database\Session;
use Exception;
use RuntimeException;

class History
{
    private readonly ChangeView $changeView;
    private readonly Session $session;

    /**
     * @param Schema $schema
     * @param TemplateEngine $templateEngine
     * @param string $application
     */
    public function __construct(
        private readonly Schema $schema,
        private readonly TemplateEngine $templateEngine,
        private readonly string $application)
    {
        Assert::notEmpty($this->application, 'application');
        $this->session = $this->schema->session();
    }

    public function getChangeView(): ChangeView
    {
        if (!isset($this->changeView))
            $this->changeView = new ChangeView($this->schema);
        return $this->changeView;
    }

    /**
     * @throws Exception
     */
    public function createDeployment(DeploymentCommand $command, DeploymentTag $tag,
        Author $deployer, bool $isDryRun, bool $isSingleTransactionMode): Deployment
    {
        return new Deployment($this->schema, $command, $tag, $deployer, $this->application, $isDryRun, $isSingleTransactionMode);
    }

    /**
     * @throws Exception
     */
    public function create(): void
    {
        $state = $this->schema->state();

        if ($state & Schema::OBJECTS)
            throw new SchemaConflictException(
                "history schema '{$this->schema->name()}' already contains cinch objects");

        /* cinch becomes the creator when the schema does not exist */
        $creator = $state & Schema::EXISTS ? 0 : Schema::CREATOR;

        if (!$this->schema->autoCreate() && $creator)
            throw new SchemaConflictException("history.schema.auto_create is disabled and schema " .
                "'{$this->schema->name()}' does not exist. Please create schema or select an existing one.");

        $Q = $this->session->quoteString(...);
        $version = $this->schema->version();
        $inList = fn (array $values) => implode(", ", array_map(fn ($v) => $Q($v->value), $values));

        $ddl = $this->templateEngine->renderTemplate($this->getPlatformTemplate(), [
            'schema' => $this->session->quoteIdentifier($this->schema->name()),
            'schema_creator' => !!$creator,
            'schema_version' => $Q($version->version),
            'schema_description' => $Q($version->description),
            'release_date' => $Q($version->releaseDate->format('Y-m-d')),
            'created_at' => $Q($this->session->getPlatform()->formatDateTime()),
            'commands' => $inList(DeploymentCommand::cases()),
            'statuses' => $inList(ChangeStatus::cases()),
            'migrate_policies' => $inList(MigratePolicy::cases()),
            ...$this->getPlatformContext()
        ]);

        $withinTransaction = $this->beginSchema();

        try {
            $this->session->executeStatement($ddl);
            $this->commitSchema();
            $this->schema->setState(Schema::EXISTS | Schema::OBJECTS | $creator);
        }
        catch (Exception $e) {
            silent_call(function () use ($withinTransaction, $creator) {
                if ($withinTransaction)
                    $this->session->rollBack();
                else
                    $this->deleteHistory(!!$creator);
            });

            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function delete(): void
    {
        $this->assertSchema();
        $this->deleteHistory(($this->schema->state() & Schema::CREATOR) != 0);
    }

    private function getPlatformTemplate(): string
    {
        $p = $this->session->getPlatform();

        $name = match ($name = $p->getName()) {
            'mariadb' => 'mysql',
            'mysql' => $p->supportsCheckConstraints() ? 'mysql' : 'mysql-no-checks',
            default => $name
        };

        return "create-history/$name.sql";
    }

    private function getPlatformContext(): array
    {
        $context = $this->schema->objects();
        $name = $this->session->getPlatform()->getName();
        $version = $this->session->getPlatform()->getVersion();

        if ($name == 'pgsql') {
            $context['ascii'] = '"C"';
            $context['utf8ci'] = $context[Schema::COLLATION];
            $context['semver_pattern'] = SchemaVersion::SEMVER_PATTERN;
        }
        else if ($name == 'mysql') {
            $context['ascii'] = 'binary';
            $context['utf8ci'] = version_compare($version, '8.0', '<')
                ? 'utf8mb4_unicode_520_ci' : 'utf8mb4_0900_ai_ci';
            $context['semver_pattern'] = str_replace('\\', '\\\\', SchemaVersion::SEMVER_PATTERN);
        }
        else if ($name == 'mariadb') {
            $context['ascii'] = 'ascii_nopad_bin';
            $context['utf8ci'] = version_compare($version, '10.10', '<')
                ? 'utf8mb4_unicode_520_nopad_ci' : 'uca1400_nopad_ai_ci';
            $context['semver_pattern'] = str_replace('\\', '\\\\', SchemaVersion::SEMVER_PATTERN);
        }
        else if ($name == 'sqlsrv') {
            $context['ascii'] = 'Latin1_General_100_BIN';
            $context['utf8ci'] = 'Latin1_General_100_CI_AI';
        }
        else if ($name == 'sqlite') {
            $context['ascii'] = 'binary';
            $context['utf8ci'] = 'nocase';
        }

        unset($context[Schema::COLLATION]);
        return $context;
    }

    /**
     * @throws Exception
     */
    private function deleteHistory(bool $schemaCreator): void
    {
        $objects = $this->schema->objects();
        $collation = array_pop($objects);
        $ddl = array_map(fn ($n) => "drop table if exists $n", $objects);

        if ($this->session->getPlatform()->getName() == 'pgsql')
            $ddl[] = "drop collation if exists $collation";

        if ($schemaCreator)
            $ddl[] = 'drop schema ' . $this->session->quoteIdentifier($this->schema->name());

        $withinTransaction = $this->beginSchema();

        try {
            $this->session->executeStatement(implode(';', $ddl));
            $this->commitSchema();
            $this->schema->setState($schemaCreator ? 0 : Schema::EXISTS);
        }
        catch (Exception $e) {
            if ($withinTransaction)
                silent_call($this->session->rollBack(...));
            throw $e;
        }
    }

    /**
     * @return bool true if a transaction was opened and false otherwise
     * @throws Exception
     */
    private function beginSchema(): bool
    {
        if ($txn = $this->session->getPlatform()->supportsTransactionalDDL())
            $this->session->beginTransaction();
        return $txn;
    }

    /**
     * @throws Exception
     */
    private function commitSchema(): void
    {
        if ($this->session->getPlatform()->supportsTransactionalDDL())
            $this->session->commit();
    }

    private function assertSchema(): void
    {
        if (!($this->schema->state() & Schema::OBJECTS))
            throw new RuntimeException("cinch history '{$this->schema->name()}' contains no objects");
    }
}
