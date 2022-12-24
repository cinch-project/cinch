<?php

namespace Cinch\Project;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;
use Exception;

class Project
{
    /**
     * @throws Exception
     */
    public function __construct(
        private readonly ProjectId $id,
        private readonly ProjectName $name,
        private Dsn $migrationStoreDsn,
        private EnvironmentMap $envMap,
        private array $hooks = [],
        private readonly bool $isSingleTransactionMode = true)
    {
    }

    /** Indicates if all migrations within a deployment, should be wrapped within a single transaction. When this
     * is false, each migration uses a separate transaction, meaning if a migration script fails, any previously
     * committed changes remain. The default is true. When using a database without transactional DDL support,
     * like mysql/mariadb and oracle, it is recommended to set this to false when any migration contains DDL.
     * @return bool
     */
    public function isSingleTransactionMode(): bool
    {
        return $this->isSingleTransactionMode;
    }

    /**
     * @return EnvironmentMap
     */
    public function getEnvironmentMap(): EnvironmentMap
    {
        return $this->envMap;
    }

    /**
     * @throws Exception
     */
    public function addEnvironment(string $name, Environment $environment): void
    {
        $this->envMap = $this->envMap->add($name, $environment);
    }

    /**
     * @throws Exception
     */
    public function removeEnvironment(string $name): void
    {
        $this->envMap = $this->envMap->remove($name);
    }


    public function getId(): ProjectId
    {
        return $this->id;
    }

    public function getName(): ProjectName
    {
        return $this->name;
    }

    public function getMigrationStoreDsn(): Dsn
    {
        return $this->migrationStoreDsn;
    }

    /**
     * @return Hook[]
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    public function snapshot(): array
    {
        $hooks = [];
        foreach ($this->hooks as $name => $hook)
            $hooks[$name] = $hook->snapshot();

        return [
            'migration_store' => (string) $this->migrationStoreDsn,
            'single_transaction' => $this->isSingleTransactionMode,
            'environments' => $this->envMap->snapshot(),
            'hooks' => (object) $hooks
        ];
    }
}