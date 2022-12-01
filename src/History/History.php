<?php

namespace Cinch\History;

use Cinch\Common\MigratePolicy;
use Cinch\Database\Identifier;
use Cinch\Database\Session;
use Cinch\MigrationStore\Migration;
use Cinch\MigrationStore\Script\CanRollback;
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
    /* 'utf8_ci_ai' is a postgresql-only collation */
    private const SCHEMA_OBJECTS = ['cinch', 'deployment', 'change', 'utf8_ci_ai'];

    private readonly Identifier $schema;
    private readonly array $objects;
    private bool $schemaExists;
    private bool $schemaCreator = false;
    private bool $objectsExist = false;

    /**
     * @param Session $session
     * @param TwigEnvironment $twig
     * @param Environment $environment
     * @param SchemaVersion $schemaVersion
     * @throws Exception
     */
    public function __construct(
        private readonly Session $session,
        private readonly TwigEnvironment $twig,
        private readonly Environment $environment,
        private readonly SchemaVersion $schemaVersion)
    {
        $this->schema = $this->session->getPlatform()->createIdentifier($this->environment->schema);

        $objects = [];
        $tablePrefix = $this->environment->tablePrefix;
        foreach (self::SCHEMA_OBJECTS as $name)
            $objects[$name] = $this->session->quoteIdentifier("$this->schema.$tablePrefix$name");

        $this->objects = $objects;
        $this->verifySchema();
        $this->initTwigFilters();
    }

    /**
     * @throws Exception
     */
    public function startDeployment(Command $command, string $deployer,
        string $application, string $tag = ''): DeploymentId
    {
        $this->assertSchema();
        $this->session->getPlatform()->lockSession($this->schema->value, $this->environment->deployLockTimeout);

        try {
            $id = $this->session->insertReturningId($this->objects['deployment'], 'deployment_id', [
                'deployer' => $deployer,
                'tag' => $tag ?: null,
                'command' => $command->value,
                'application' => $application,
                'schema_version' => $this->schemaVersion->version,
                'started_at' => $this->formatDateTime()
            ]);

            return new DeploymentId($id);
        }
        catch (Exception $e) {
            ignoreException(fn() => $this->session->getPlatform()->unlockSession($this->schema->value));
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function addChange(DeploymentId $id, Migration $migration, Status $status): void
    {
        $this->assertSchema();

        $this->session->insert($this->objects['change'], [
            'change_id' => $migration->id->value,
            'deployment_id' => $id->value,
            'location' => $migration->location->value,
            'migrate_policy' => $migration->script->getMigratePolicy()->value,
            'status' => $status->value,
            'author' => $migration->script->getAuthor()->value,
            'checksum' => $migration->checksum->value,
            'description' => $migration->script->getDescription()->value,
            'can_rollback' => (int) ($migration->script instanceof CanRollback),
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
            $this->assertSchema();
            $this->session->update($this->objects['deployment'], [
                'error' => $error ? json_encode($error, JSON_UNESCAPED_SLASHES) : null,
                'ended_at' => $this->formatDateTime()
            ], ['deployment_id' => $id->value]);
        }
        finally {
            ignoreException(fn() => $this->session->getPlatform()->unlockSession($this->schema->value));
        }
    }

    /**
     * @throws Exception
     */
    public function create(SchemaVersion $schemaVersion): void
    {
        if ($this->objectsExist)
            throw new Exception("history schema '{$this->schema->value}' already exists");

        $schemaCreator = !$this->schemaExists;
        if (!$this->environment->autoCreateSchema && $schemaCreator)
            throw new RuntimeException("auto_create_schema is disabled and schema '{$this->schema->value}' " .
                "does not exist. Please create this schema or configure an existing one.");

        $Q = $this->session->quoteString(...);
        $ddl = $this->twig->render('create-history.twig', [
            'db' => [
                'name' => $this->session->getPlatform()->getName(),
                'version' => $this->session->getPlatform()->getVersion()
            ],
            'schema' => [
                'creator' => $schemaCreator,
                'name' => $this->schema->quotedId,
                'version' => $Q($schemaVersion->version),
                'description' => $Q($schemaVersion->description),
                'release_date' => $Q($schemaVersion->releaseDate->format('Y-m-d')),
                'created_at' => $Q($this->formatDateTime())
            ],
            'commands' => array_map(fn($e) => $e->value, Command::cases()),
            'statuses' => array_map(fn($e) => $e->value, Status::cases()),
            'migrate_policies' => array_map(fn($e) => $e->value, MigratePolicy::cases()),
            ...$this->objects
        ]);

        try {
            $this->session->executeStatement($ddl);
            $this->schemaExists = true;
            $this->schemaCreator = $schemaCreator;
        }
        catch (Exception $e) {
            ignoreException(fn() => $this->deleteHistory($schemaCreator));
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function delete(): void
    {
        $this->assertSchema();
        $this->deleteHistory($this->schemaCreator);
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
                'name' => $this->schema->quotedId,
            ],
            ...$this->objects
        ]);

        $this->session->executeStatement($ddl);
        $this->schemaExists = false;
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
    private function verifySchema(): void
    {
        if (!($this->schemaExists = $this->schemaExists()) || !($this->objectsExist = $this->objectsExist()))
            return;

        $row = $this->session->executeQuery("
            select schema_creator, schema_version from $this->objects['cinch'] 
                where created_at = (select max(created_at) from $this->objects['cinch'])"
        )->fetchNumeric();

        if ($row === false)
            throw new CorruptSchemaException("{$this->objects['cinch']} is empty");

        [$this->schemaCreator, $server] = $row;

        if (($n = version_compare($this->schemaVersion->version, $server)) != 0) {
            $dir = $n < 0 ? 'behind' : 'ahead of';
            throw new RuntimeException("client '{$this->schemaVersion->version}' is $dir server '$server'");
        }
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

    /** Do we have cinch schema objects or not.
     * @throws Exception if objects are found but not all of them, no exception if none are found
     */
    private function objectsExist(): bool
    {
        $tables = self::SCHEMA_OBJECTS;
        $collation = array_pop($tables);

        $result = $this->session->executeQuery("
            select lower(table_name) from information_schema.tables 
                where table_schema = ? and table_name in (?, ?, ?)", [$this->schema->value, ...$tables]);

        $found = [];
        while (($t = $result->fetchOne()) !== false)
            $found[] = $t;

        /* any cinch tables missing? */
        if ($found && ($missing = array_diff($tables, $found))) {
            $missing = implode(', ', $missing);
            throw new CorruptSchemaException("'{$this->schema->value}' missing cinch table(s) $missing");
        }

        /* postgresql also has a collation */
        if ($this->session->getPlatform()->getName() == 'pgsql') {
            $haveCollation = $this->collationExists($collation);

            if ($found && !$haveCollation)
                throw new CorruptSchemaException("'{$this->schema->value}' missing cinch collation $collation");

            if (!$found && $haveCollation) {
                $missing = implode(', ', $tables);
                throw new CorruptSchemaException("'{$this->schema->value}' missing cinch table(s) $missing");
            }
        }

        return !!$found;
    }

    /** @throws Exception */
    private function collationExists(string $collation): bool
    {
        $query = "select 1 from information_schema.collations where collation_schema = ? collation_name in (?)";
        return $this->session->executeQuery($query, [$this->schema->value, $collation])->fetchOne() !== false;
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

    private function assertSchema(): void
    {
        if (!$this->schemaExists)
            throw new RuntimeException("cinch history '{$this->schema->value}' doesn't exist");
    }
}