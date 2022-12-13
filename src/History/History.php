<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\MigratePolicy;
use Cinch\Component\Assert\Assert;
use Cinch\Database\Session;
use Exception;
use RuntimeException;
use Twig\Environment as Twig;
use Twig\TwigFilter;

class History
{
    private readonly ChangeView $changeView;
    private readonly Session $session;

    /**
     * @param Schema $schema
     * @param Twig $twig
     * @param string $application
     */
    public function __construct(
        private readonly Schema $schema,
        private readonly Twig $twig,
        private readonly string $application)
    {
        Assert::notEmpty($this->application, 'application');
        $this->session = $this->schema->session();
        $this->initTwigFilters();
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
    public function openDeployment(DeploymentCommand $command, DeploymentTag $tag, Author $deployer): Deployment
    {
        return new Deployment($this->schema, $command, $tag, $deployer, $this->application);
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
            throw new SchemaConflictException("auto_create_schema is disabled and schema " .
                "'{$this->schema->name()}' does not exist. Please create this schema or configure an existing one.");

        $Q = $this->session->quoteString(...);
        $version = $this->schema->version();

        $ddl = $this->twig->render('create-history.twig', [
            'db' => [
                'name' => $this->session->getPlatform()->getName(),
                'version' => $this->session->getPlatform()->getVersion()
            ],
            'schema' => [
                'creator' => !!$creator,
                'name' => $this->session->quoteIdentifier($this->schema->name()),
                'version' => $Q($version->version),
                'description' => $Q($version->description),
                'release_date' => $Q($version->releaseDate->format('Y-m-d')),
                'created_at' => $Q($this->session->getPlatform()->formatDateTime())
            ],
            'commands' => array_map(fn($e) => $e->value, DeploymentCommand::cases()),
            'statuses' => array_map(fn($e) => $e->value, ChangeStatus::cases()),
            'migrate_policies' => array_map(fn($e) => $e->value, MigratePolicy::cases()),
            ...$this->schema->objects()
        ]);

        $withinTransaction = $this->beginSchema();

        try {
            $this->session->executeStatement($ddl);
            $this->commitSchema();
            $this->schema->setState(Schema::EXISTS | Schema::OBJECTS | $creator);
        }
        catch (Exception $e) {
            ignoreException(function () use ($withinTransaction, $creator) {
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

    /**
     * @throws Exception
     */
    private function deleteHistory(bool $schemaCreator): void
    {
        $ddl = $this->twig->render('drop-history.twig', [
            'db' => ['name' => $this->session->getPlatform()->getName()],
            'schema' => [
                'creator' => $schemaCreator,
                'name' => $this->session->quoteIdentifier($this->schema->name()),
            ],
            ...$this->schema->objects()
        ]);

        $withinTransaction = $this->beginSchema();

        try {
            $this->session->executeStatement($ddl);
            $this->commitSchema();
            $this->schema->setState($schemaCreator ? 0 : Schema::EXISTS);
        }
        catch (Exception $e) {
            if ($withinTransaction)
                ignoreException($this->session->rollBack(...));
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

    private function initTwigFilters(): void
    {
        /* identifier quoting: {{ 'schema.table'|strip_schema }}
         *     sqlite: table
         *     others: schema.table
         *
         * sqlite doesn't allow schema-qualified tables for INDEX ON or REFERENCES clauses. It throws
         * a syntax error on the ".". This only strips for sqlite [sighs]. It does allow schema-qualified
         * index names: `CREATE INDEX schema.my_index_name ON my_table (column)`. Template handles that.
         */
        $this->twig->addFilter(new TwigFilter('strip_schema', function (string $string) {
            if ($this->session->getPlatform()->getName() == 'sqlite')
                $string = explode('.', $string, 2)[1];
            return $string;
        }));

        /* identifier quoting: {{ 'identifier_name'|quote }} */
        $this->twig->addFilter(new TwigFilter('quote', function (string $string) {
            return $this->session->quoteIdentifier($string);
        }));

        /* varchar column: {{ 'name'|varchar(255) }}
         *     others: name varchar(255)
         *     sqlite: name text constraint "'name' value too long for varchar(255)" check (length(name) between 0 and 255)
         */
        $this->twig->addFilter(new TwigFilter('varchar', function (string $name, int $len) {
            return $this->renderCharacterDefinition($name, 'varchar', $len);
        }));

        /* varchar column: {{ 'name'|nvarchar(255) }}
         *     others: name varchar(255)
         *     mssql: name nvarchar(255) (uses national varying character UCS2|UTF-16)
         *     sqlite: name text constraint "'name' value too long for varchar(255)" check (length(name) between 0 and 255)
         */
        $this->twig->addFilter(new TwigFilter('nvarchar', function (string $name, int $len) {
            $type = $this->session->getPlatform()->getName() == 'mssql' ? 'nvarchar' : 'varchar';
            return $this->renderCharacterDefinition($name, $type, $len);
        }));
    }

    private function renderCharacterDefinition(string $name, string $type, int $len): string
    {
        $error = "'$name' value too long for $type($len)";
        return match ($this->session->getPlatform()->getName()) {
            'sqlite' => "$name text constraint \"$error\" check (length($name) between 0 and $len)",
            default => "$name $type($len)"
        };
    }

    private function assertSchema(): void
    {
        if (!($this->schema->state() & Schema::OBJECTS))
            throw new RuntimeException("cinch history '{$this->schema->name()}' contains no objects");
    }
}