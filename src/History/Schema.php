<?php

namespace Cinch\History;

use Cinch\Common\Environment;
use Cinch\Database\Session;
use Exception;
use RuntimeException;

class Schema
{
    const EXISTS = 0x01;  // schema exists
    const CREATOR = 0x02; // cinch owns schema (created it)
    const OBJECTS = 0x04; // cinch schema objects exist
    private const STATE_MASK = 7;

    private const TABLES = ['cinch', 'deployment', 'change'];
    /** postgresql-only non-deterministic collation */
    private const COLLATION = 'utf8_ci_ai';

    /** Object names: quoted and schema-qualified. "$schema"."$prefix$name" */
    private readonly array $objects;
    /** Raw object names: unquoted and not schema-qualified. $prefix$name */
    private readonly array $rawObjects;
    /** state mask */
    private int $state = 0;
    /** schema name */
    private readonly string $name;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly Session $session,
        private readonly Environment $environment,
        private readonly SchemaVersion $version)
    {
        $this->name = $this->environment->schema;
        [$this->objects, $this->rawObjects] = $this->createObjects();
        $this->verify();
    }

    public function session(): Session
    {
        return $this->session;
    }

    public function autoCreate(): bool
    {
        return $this->environment->autoCreate;
    }

    public function setState(int $state): void
    {
        $this->state = $state & self::STATE_MASK;
    }

    public function state(): int
    {
        return $this->state;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): SchemaVersion
    {
        return $this->version;
    }

    /** Gets the quoted and schema-qualified table.
     * @param string $name
     * @return string
     */
    public function table(string $name): string
    {
        if (in_array($name, self::TABLES))
            return $this->objects[$name];
        throw new RuntimeException("history: unknown table '$name'");
    }

    /** Gets the raw (unquoted and without schema) table.
     * @param string $name
     * @return string
     */
    public function rawTable(string $name): string
    {
        if (in_array($name, self::TABLES))
            return $this->rawObjects[$name];
        throw new RuntimeException("history: unknown table '$name'");
    }

    public function objects(): array
    {
        return $this->objects;
    }

    /**
     * @throws Exception
     */
    public function lock(): bool
    {
        return $this->session->lock($this->name, $this->environment->deployTimeout);
    }

    /**
     * @throws Exception
     */
    public function unlock(): void
    {
        $this->session->unlock($this->name);
    }

    /**
     * @throws Exception
     */
    private function verify(): void
    {
        if (!$this->schemaExists() || !$this->objectsExist())
            return;

        $cinch = $this->table('cinch');
        $row = $this->session->executeQuery(
            "select schema_creator, schema_version from $cinch where created_at = (select max(created_at) from $cinch)"
        )->fetchNumeric();

        if ($row === false)
            throw new CorruptSchemaException("$cinch is empty");

        [$creator, $server] = $row;

        if ($creator)
            $this->state |= self::CREATOR;

        if (($n = version_compare($this->version->version, $server)) != 0) {
            $dir = $n < 0 ? 'behind' : 'ahead of';
            throw new RuntimeException("client '{$this->version->version}' is $dir server '$server'");
        }
    }

    private function createObjects(): array
    {
        $objects = [];
        $rawObjects = [];
        $fullPrefix = "$this->name.{$this->environment->tablePrefix}";

        foreach (self::TABLES as $name) {
            $rawObjects[$name] = $this->environment->tablePrefix . $name;
            $objects[$name] = $this->session->quoteIdentifier($fullPrefix . $name);
        }

        $rawObjects[self::COLLATION] = $this->environment->tablePrefix . self::COLLATION;
        $objects[self::COLLATION] = $this->session->quoteIdentifier($fullPrefix . self::COLLATION);
        return [$objects, $rawObjects];
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function schemaExists(): bool
    {
        $exists = $this->session->getPlatform()->getName() == 'sqlite';

        if (!$exists) {
            $query = 'select 1 from information_schema.schemata where schema_name = ?';
            $exists = $this->session->executeQuery($query, [$this->name])->fetchNumeric() !== false;
        }

        if ($exists)
            $this->state |= self::EXISTS;

        return $exists;
    }

    /** Do we have cinch schema objects or not. All or nothing are success cases.
     * @throws Exception if only some objects are found
     */
    private function objectsExist(): bool
    {
        $tables = $this->rawObjects;
        $collation = array_pop($tables);
        $platformName = $this->session->getPlatform()->getName();

        if ($platformName == 'sqlite')
            $result = $this->session->executeQuery("select tbl_name from sqlite_master 
                where type = 'table' and tbl_name in (?, ?, ?)", $tables);
        else
            $result = $this->session->executeQuery("select lower(table_name) from information_schema.tables 
                where table_schema = ? and table_name in (?, ?, ?)", [$this->name, ...$tables]);

        $foundTables = [];
        while (($t = $result->fetchOne()) !== false)
            $foundTables[] = $t;

        /* any cinch tables missing? */
        if ($foundTables && ($missing = array_diff($tables, $foundTables)))
            throw new CorruptSchemaException(sprintf("'%s' missing cinch table(s) %s",
                $this->name, implode(', ', $missing)));

        /* postgresql also has a collation */
        if ($platformName == 'pgsql') {
            $haveCollation = $this->collationExists($collation);

            if ($foundTables && !$haveCollation)
                throw new CorruptSchemaException("'$this->name' missing cinch collation $collation");

            if (!$foundTables && $haveCollation)
                throw new CorruptSchemaException(sprintf("'%s' missing cinch table(s) %s",
                    $this->name, implode(', ', $tables)));
        }

        $exists = !!$foundTables;

        if ($exists)
            $this->state |= self::OBJECTS;

        return $exists;
    }

    /** @throws Exception */
    private function collationExists(string $collation): bool
    {
        $query = "select 1 from information_schema.collations where collation_schema = ? and collation_name = ?";
        return $this->session->executeQuery($query, [$this->name, $collation])->fetchOne() !== false;
    }
}