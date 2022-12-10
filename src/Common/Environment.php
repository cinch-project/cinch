<?php

namespace Cinch\Common;

class Environment
{
    const DEFAULT_SCHEMA_FORMAT = 'cinch_%s';
    const DEFAULT_DEPLOY_LOCK_TIMEOUT = 10;
    const DEFAULT_AUTO_CREATE_SCHEMA = true;

    public readonly string $schema;
    public readonly bool $autoCreateSchema;

    public function __construct(
        public readonly Dsn $targetDsn,
        public readonly Dsn $historyDsn,
        string $schema,
        public readonly string $tablePrefix,
        public readonly int $deployLockTimeout,
        bool $autoCreateSchema)
    {
        /* these options are not supported in sqlite, it has a 'main' schema and you cannot create more. You
         * would have to attach another db file.
         */
        if ($this->historyDsn->getScheme() == 'sqlite') {
            $schema = 'main';
            $autoCreateSchema = false;
        }

        $this->schema = $schema;
        $this->autoCreateSchema = $autoCreateSchema;
    }

    public function normalize(): array
    {
        $data = [
            'deploy_lock_timeout' => $this->deployLockTimeout,
            'target' => (string) $this->targetDsn,
            'history' => [
                'schema' => $this->schema,
                'table_prefix' => $this->tablePrefix,
                'auto_create_schema' => $this->autoCreateSchema
            ]
        ];

        if (!$this->targetDsn->equals($this->historyDsn))
            $data['history']['dsn'] = (string) $this->historyDsn;

        return $data;
    }
}