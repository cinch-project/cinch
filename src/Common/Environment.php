<?php

namespace Cinch\Common;

use Cinch\Database\DatabaseDsn;

class Environment
{
    const DEFAULT_SCHEMA_FORMAT = 'cinch_%s';
    const DEFAULT_DEPLOY_TIMEOUT = 10;
    const DEFAULT_AUTO_CREATE = true;

    public readonly string $schema;
    public readonly bool $autoCreate;

    public function __construct(
        public readonly DatabaseDsn $targetDsn,
        public readonly DatabaseDsn $historyDsn,
        string $schema,
        public readonly string $tablePrefix,
        public readonly int $deployTimeout,
        bool $autoCreate)
    {
        /* these options are not supported in sqlite, it has a 'main' schema and you cannot create more. You
         * would have to attach another db file.
         */
        if ($this->historyDsn->driver == 'sqlite') {
            $schema = 'main';
            $autoCreate = false;
        }

        $this->schema = $schema;
        $this->autoCreate = $autoCreate;
    }

    public function snapshot(): array
    {
        $dsn = $this->targetDsn != $this->historyDsn ? $this->historyDsn->snapshot() : [];
        return [
            'deploy_timeout' => $this->deployTimeout,
            'target' => $this->targetDsn->snapshot(),
            'history' => [
                ...$dsn,
                'schema' => [
                    'name' => $this->schema,
                    'table_prefix' => $this->tablePrefix,
                    'auto_create' => $this->autoCreate
                ]
            ]
        ];
    }
}