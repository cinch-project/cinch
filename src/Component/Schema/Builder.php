<?php

namespace Cinch\Component\Schema;

use Exception;

class Builder
{
    /**
     * @param Session $session
     * @throws Exception
     */
    public function __construct(protected readonly Session $session)
    {
        // sqlsrv: alter table alter column <col_def>
        // pgsql: alter table alter column TYPE|SET DEFAULT|DROP DEFAULT|(SET | DROP) NOT NULL|
        // sqlite: limited to table|column renames, add and drop column, must create new table, copy, adjust, drop old
        // mysql: alter table modify <col_def>
    }

    public function getSession(): Session
    {
        return $this->session;
    }

    public function create(string $schema): void
    {
        // create schema
    }

    /**
     * @throws Exception
     */
    public function createTable(string $name, string $options = ''): Table
    {
        if ($this->session->tableExists($name))
            throw new Exception("cannot create table '$name' - already exists");
        return new Table($this->session, $name, $options);
    }

    /**
     * @throws Exception
     */
    public function alterTable(string $name): AlterTable
    {
        return new AlterTable($this->session, $name);
    }

    public function renameTable(string $name): void
    {

    }

    public function dropTable(string $name): void
    {

    }

    public function copyTable(string $source, string $target): void
    {

    }
}
