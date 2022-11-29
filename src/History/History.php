<?php

namespace Cinch\History;

use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\Script\Revertable;
use Cinch\Common\CommitPolicy;
use Cinch\Database\Identifier;
use Cinch\Database\Session;
use Cinch\Project\Environment;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use RuntimeException;
use Twig\Environment as TwigEnvironment;
use Twig\TwigFilter;

class History
{
    private const SCHEMA_OBJECTS = ['cinch', 'deployment', 'change', 'utf8_ci_ai'];

    private readonly Identifier $schema;
    private readonly array $schemaObjects;
    private bool $schemaExists;

    /**
     * @param Session $session
     * @param SchemaVersion $schemaVersion
     * @param TwigEnvironment $twig
     * @param string $application
     * @param Environment $environment
     * @throws Exception
     */
    public function __construct(
        private readonly Session $session,
        private readonly SchemaVersion $schemaVersion,
        private readonly TwigEnvironment $twig,
        private readonly string $application,
        private readonly Environment $environment)
    {
        $this->schema = $this->session->getPlatform()->createIdentifier($this->environment->schema);

        $objects = [];
        $tablePrefix = $this->environment->tablePrefix;
        foreach (self::SCHEMA_OBJECTS as $name)
            $objects[$name] = $this->session->quoteIdentifier("$this->schema.$tablePrefix$name");

        $this->schemaObjects = $objects;
        $this->schemaExists = $this->schemaExists();
        $this->checkVersion();
        $this->initTwigFilters();
    }

    /**
     * @throws Exception
     */
    public function startDeployment(Command $command, string $deployer, string $tag = ''): DeploymentId
    {
        $this->session->getPlatform()->lockSession($this->schema->value, $this->environment->deployLockTimeout);

        try {
            $id = $this->session->insertFetchId($this->schemaObjects['deployment'], 'deployment_id', [
                'deployer' => $deployer,
                'tag' => $tag ?: null,
                'command' => $command->value,
                'application' => $this->application,
                'schema_version' => $this->schemaVersion->version,
                'started_at' => $this->formatDateTime()
            ]);

            return new DeploymentId($id);
        }
        catch (Exception $e) {
            $this->session->getPlatform()->unlockSession($this->schema->value);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function addChange(DeploymentId $id, Migration $migration, string $status): void
    {
        $this->session->insert($this->schemaObjects['change'], [
            'change_id' => $migration->id->value,
            'deployment_id' => $id->value,
            'location' => $migration->location->value,
            'commit_policy' => $migration->script->getCommitPolicy()->value,
            'status' => $status,
            'author' => $migration->script->getAuthor()->value,
            'checksum' => $migration->checksum->value,
            'description' => $migration->script->getDescription()->value,
            'revertable' => (int) ($migration->script instanceof Revertable),
            'is_sql' => (int) (pathinfo($migration->location->value, PATHINFO_EXTENSION) == 'sql'),
            'authored_at' => $this->formatDateTime($migration->script->getAuthoredAt()),
            'deployed_at' => $this->formatDateTime()
        ]);
    }

    /**
     * @throws Exception
     */
    public function endDeployment(DeploymentId $id, array $error = []): void
    {
        try {
            $this->session->update($this->schemaObjects['deployment'], [
                'error' => $error ? json_encode($error, JSON_UNESCAPED_SLASHES) : null,
                'ended_at' => $this->formatDateTime()
            ], ['deployment_id' => $id->value]);
        }
        finally {
            $this->session->getPlatform()->unlockSession($this->schema->value);
        }
    }

    /**
     * @throws Exception
     */
    public function create(): void
    {
        $isSchemaCreator = !$this->schemaExists; // track if cinch will be the schema creator
        $Q = $this->session->quoteString(...);

        if (!$this->environment->autoCreateSchema && $isSchemaCreator)
            throw new RuntimeException("auto_create_schema is disabled and schema '{$this->schema->value}' " .
                "does not exist. Please create this schema or configure an existing one.");

        $ddl = $this->twig->render('create-history.twig', [
            'db' => [
                'name' => $this->session->getPlatform()->getName(),
                'version' => $this->session->getPlatform()->getVersion()
            ],
            'schema' => [
                'is_creator' => $isSchemaCreator,
                'name' => $this->schema->quotedId,
                'version' => $Q($this->schemaVersion->version),
                'description' => $Q($this->schemaVersion->description),
                'release_date' => $Q($this->schemaVersion->releaseDate->format('Y-m-d')),
                'created_at' => $Q($this->formatDateTime())
            ],
            'commit_policies' => array_map(fn($e) => $e->value, CommitPolicy::cases()),
            ...$this->schemaObjects
        ]);

        try {
            $this->session->executeStatement($ddl);
            $this->schemaExists = true;
        }
        catch (Exception $e) {
            $this->deleteHistory($isSchemaCreator);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function delete(): void
    {
        $this->deleteHistory($this->isSchemaCreator());
    }

    /**
     * @throws Exception
     */
    private function deleteHistory(bool $isSchemaCreator): void
    {
        $ddl = $this->twig->render('drop-history.twig', [
            'db' => [
                'name' => $this->session->getPlatform()->getName(),
                'version' => $this->session->getPlatform()->getVersion()
            ],
            'schema' => [
                'is_creator' => $isSchemaCreator,
                'name' => $this->schema->quotedId,
            ],
            ...$this->schemaObjects
        ]);

        $this->session->executeStatement($ddl);
        $this->schemaExists = false;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function schemaExists(): bool
    {
        if ($this->session->getPlatform()->getName() == 'sqlite')
            return true;

        $query = 'select 1 from information_schema.schemata where schema_name = ?';
        return $this->session->executeQuery($query, [$this->schema->value])->fetchNumeric() !== false;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function isSchemaCreator(): bool
    {
        $query = "select schema_creator from {$this->schemaObjects['cinch']} where schema_version = ?";
        return $this->session->executeQuery($query, [$this->schemaVersion->version])->fetchOne() == 1;
    }

    /**
     * @throws Exception
     */
    private function formatDateTime(DateTimeInterface|null $dt = null): string
    {
        $tz = new DateTimeZone('UTC');
        return $this->session->getPlatform()->formatDateTime($dt ?? new DateTime(timezone: $tz));
    }

    /**
     * @throws Exception
     */
    private function checkVersion(): void
    {
        if (!$this->schemaExists)
            return;

        $cinch = $this->schemaObjects['cinch'];
        $client = $this->schemaVersion->version;

        /* fetch active version (last applied). note: if downgraded, this might not be the newest version */
        $server = $this->session->executeQuery("select schema_version from $cinch 
            where created_at = (select max(created_at) from $cinch)")->fetchOne();

        if (($n = version_compare($client, $server)) != 0) {
            $dir = $n < 0 ? 'behind' : 'ahead of';
            throw new RuntimeException("client '$client' is $dir server '$server'");
        }
    }

    private function initTwigFilters(): void
    {
        /* identifier quoting: {{ 'identifier_name'|quote }} */
        $this->twig->addFilter(new TwigFilter('quote', function (string $string) {
            return $this->session->quoteIdentifier($string);
        }));

        /* varchar column: {{ 'name'|varchar(255) }}
         *     name varchar(255)
         *     sqlite: name text constraint "'name' value too long for varchar(255)" check (length(name) between 0 and 255)
         */
        $this->twig->addFilter(new TwigFilter('varchar', function (string $name, int $len) {
            return $this->renderCharacterDefinition($name, 'varchar', $len);
        }));

        /* varchar column: {{ 'name'|nvarchar(255) }}
         *     name varchar(255)
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
}