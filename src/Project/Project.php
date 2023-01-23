<?php

namespace Cinch\Project;

use Cinch\Common\Environment;
use Cinch\Component\Assert\AssertException;
use Cinch\Hook\Hook;
use Cinch\MigrationStore\StoreDsn;
use Exception;

class Project
{
    /**
     * @param ProjectName $name
     * @param StoreDsn $migrationStoreDsn
     * @param EnvironmentMap $envMap
     * @param Hook[] $hooks
     * @param bool $isSingleTransactionMode
     * @throws Exception
     */
    public function __construct(
        private readonly ProjectName $name,
        private StoreDsn $migrationStoreDsn,
        private EnvironmentMap $envMap,
        private array $hooks = [],
        private readonly bool $isSingleTransactionMode = true)
    {
        foreach ($this->hooks as $i => $h)
            if (!($h instanceof Hook))
                throw new AssertException("hooks[$i] is not an instance of " . Hook::class);
    }

    /** Indicates if all migrations within a deployment, should be wrapped within a single transaction. When this
     * is false, each migration uses a separate transaction, meaning if a migration script fails, any previously
     * committed changes remain. The default is true. When using a database without transactional DDL support,
     * like mysql/mariadb and oracle, it is recommended to set this to false when any migration contains DDL.
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

    public function getName(): ProjectName
    {
        return $this->name;
    }

    public function getMigrationStoreDsn(): StoreDsn
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
        foreach ($this->hooks as $hook)
            $hooks[] = $hook->snapshot();

        return [
            'name' => $this->name->value,
            'migration_store' => $this->migrationStoreDsn->snapshot(),
            'single_transaction' => $this->isSingleTransactionMode,
            'environments' => $this->envMap->snapshot(),
            'hooks' => $hooks
        ];
    }
}