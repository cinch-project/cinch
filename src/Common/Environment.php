<?php

namespace Cinch\Common;

class Environment
{
    const DEFAULT_SCHEMA_FORMAT = 'cinch_%s';
    const DEFAULT_DEPLOY_TIMEOUT = 10;
    const DEFAULT_CREATE_SCHEMA = true;

    public readonly string $schema;
    public readonly bool $createSchema;

    public function __construct(
        public readonly Dsn $targetDsn,
        public readonly Dsn $historyDsn,
        string $schema,
        public readonly string $tablePrefix,
        public readonly int $deployTimeout,
        bool $createSchema)
    {
        /* these options are not supported in sqlite, it has a 'main' schema and you cannot create more. You
         * would have to attach another db file.
         */
        if ($this->historyDsn->getScheme() == 'sqlite') {
            $schema = 'main';
            $createSchema = false;
        }

        $this->schema = $schema;
        $this->createSchema = $createSchema;
    }

    public function normalize(): array
    {
        $data = [
            'deploy_timeout' => $this->deployTimeout,
            'target' => (string) $this->targetDsn,
            'history' => [
                'schema' => $this->schema,
                'table_prefix' => $this->tablePrefix,
                'create_schema' => $this->createSchema
            ]
        ];

        if (!$this->targetDsn->equals($this->historyDsn))
            $data['history']['dsn'] = (string) $this->historyDsn;

        return $data;
    }
}