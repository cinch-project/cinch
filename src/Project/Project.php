<?php

namespace Cinch\Project;

use Cinch\Common\Dsn;
use Cinch\Common\Environment;

class Project
{
    public function __construct(
        private readonly ProjectId $id,
        private readonly ProjectName $name,
        protected Dsn $migrationStore,
        protected EnvironmentMap $environments,
        protected array $hooks = [])
    {
    }

    public function getEnvironment(string $name = ''): Environment
    {
        return $this->environments->get($name ?: $this->environments->getDefaultName());
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
            'environments' => $this->environments->normalize(),
            'hooks' => (object) $hooks
        ];
    }
}