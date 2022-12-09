<?php

namespace Cinch\History;

use Cinch\Common\Author;
use Cinch\Common\Location;
use Cinch\Common\MigratePolicy;
use Cinch\Component\Assert\Assert;
use Cinch\Database\Session;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Result;
use Exception;
use RuntimeException;
use Twig\Environment as Twig;
use Twig\TwigFilter;

class History
{
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

    /**
     * @throws Exception
     */
    public function startDeployment(Command $command, Author $deployer, string|null $tag = null): DeploymentId
    {
        $this->assertSchema();
        $this->schema->lock();

        try {
            $table = $this->schema->table('deployment');
            $id = $this->session->insertReturningId($table, 'deployment_id', [
                'deployer' => $deployer->value,
                'tag' => $tag,
                'command' => $command->value,
                'application' => $this->application,
                'schema_version' => $this->schema->version()->version,
                'started_at' => $this->formatDateTime()
            ]);

            return new DeploymentId($id);
        }
        catch (Exception $e) {
            ignoreException($this->schema->unlock(...));
            throw $e;
        }
    }

    /**
     * @param Location $location
     * @return Change|null
     * @throws Exception
     */
    public function getLatestChangeForLocation(Location $location): Change|null
    {
        $row = $this->session->executeQuery("
            select * from {$this->schema->table('change')}
                where location = ? order by deployed_at desc limit 1", [$location->value]
        )->fetchAssociative();

        return $row ? Change::denormalize($row) : null;
    }

    /** Gets changes since the given tag. This is used by rollbacks.
     * Changes marked as rollbacked are not included.
     * @param string $tag
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getChangesSinceTag(string $tag): array
    {
        if (!$tag)
            return [];

        $r = $this->session->executeQuery("
            select * from {$this->schema->table('change')}  
                where deployed_at > (select ended_at from {$this->schema->table('deployment')} where tag = ?)
                    and status <> ? order by deployed_at desc", [$tag, Status::ROLLBACKED]
        );

        return $this->getChangesFromResult($r);
    }

    /** Gets changes since the given datetime. This is used by rollbacks.
     * Changes marked as rollbacked are not included.
     * @param DateTimeInterface $date
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getChangesSinceDate(DateTimeInterface $date): array
    {
        $date = $this->formatDateTime($date);

        $r = $this->session->executeQuery("
            select * from {$this->schema->table('change')} 
                where deployed_at > ? and status <> ? order by deployed_at desc", [$date, Status::ROLLBACKED]
        );

        return $this->getChangesFromResult($r);
    }

    /** Gets the last N number of changes. This is used by rollbacks.
     * Changes marked as rollbacked are not included.
     * @param int $count
     * @return Change[] ordered by deployed time descending
     * @throws Exception
     */
    public function getLastChanges(int $count): array
    {
        if ($count <= 0)
            return [];

        $r = $this->session->executeQuery("
            select * from {$this->schema->table('change')} 
                where status <> ? order by deployed_at desc limit $count", [Status::ROLLBACKED]
        );

        return $this->getChangesFromResult($r);
    }

    /**
     * @throws Exception
     */
    public function addChange(Change $change): void
    {
        $this->assertSchema();
        $data = $change->normalize($this->formatDateTime(...));
        $this->session->insert($this->schema->table('change'), $data);
    }

    /**
     * @throws Exception
     */
    public function endDeployment(DeploymentId $id, array $error = []): void
    {
        try {
            $this->assertSchema();
            $this->session->update($this->schema->table('deployment'), [
                'error' => $error ? json_encode($error, JSON_UNESCAPED_SLASHES) : null,
                'ended_at' => $this->formatDateTime()
            ], ['deployment_id' => $id->value]);
        }
        finally {
            ignoreException($this->schema->unlock(...));
        }
    }

    /**
     * @throws Exception
     */
    public function create(): void
    {
        $state = $this->schema->state();

        if ($state & Schema::OBJECTS)
            throw new Exception("history schema '{$this->schema->name()}' already contains cinch objects");

        /* cinch becomes the creator when the schema does not exist */
        $creator = $state & Schema::EXISTS ? 0 : Schema::CREATOR;

        if (!$this->schema->autoCreate() && $creator)
            throw new RuntimeException("auto_create_schema is disabled and schema '{$this->schema->name()}' " .
                "does not exist. Please create this schema or configure an existing one.");

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
                'created_at' => $Q($this->formatDateTime())
            ],
            'commands' => array_map(fn($e) => $e->value, Command::cases()),
            'statuses' => array_map(fn($e) => $e->value, Status::cases()),
            'migrate_policies' => array_map(fn($e) => $e->value, MigratePolicy::cases()),
            ...$this->schema->objects()
        ]);

        $withinTransaction = $this->beginCinchSchema();

        try {
            $this->session->executeStatement($ddl);
            $this->commitCinchSchema();
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

        $withinTransaction = $this->beginCinchSchema();

        try {
            $this->session->executeStatement($ddl);
            $this->commitCinchSchema();
            $this->schema->setState($schemaCreator ? 0 : Schema::EXISTS);
        }
        catch (Exception $e) {
            if ($withinTransaction)
                ignoreException($this->session->rollBack(...));
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    private function getChangesFromResult(Result $r): array
    {
        $changes = [];

        while ($row = $r->fetchAssociative())
            $changes[] = Change::denormalize($row);

        return $changes;
    }

    /**
     * @throws Exception
     */
    private function formatDateTime(DateTimeInterface|null $dt = null): string
    {
        if (!$dt)
            $dt = new DateTime(timezone: new DateTimeZone('UTC'));
        return $this->session->getPlatform()->formatDateTime($dt);
    }

    /**
     * @return bool true if a transaction was opened and false otherwise
     * @throws Exception
     */
    private function beginCinchSchema(): bool
    {
        if ($txn = $this->session->getPlatform()->supportsTransactionalDDL())
            $this->session->beginTransaction();
        return $txn;
    }

    /**
     * @throws Exception
     */
    private function commitCinchSchema(): void
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
        if (!($this->schema->state() & Schema::OBJECTS))
            throw new RuntimeException("cinch history '{$this->schema->name()}' contains no objects");
    }
}