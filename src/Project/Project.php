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
        protected Dsn $migrationStore,
        protected EnvironmentMap $envMap,
        protected array $hooks = [])
    {
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

    public function getMigrationStore(): Dsn
    {
        return $this->migrationStore;
    }

    /**
     * @return Hook[]
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    public function normalize(): array
    {
        $hooks = [];
        foreach ($this->hooks as $name => $hook)
            $hooks[$name] = $hook->normalize();

        return [
            'migration_store' => (string) $this->migrationStore,
            'environments' => $this->envMap->normalize(),
            'hooks' => (object) $hooks
        ];
    }
}