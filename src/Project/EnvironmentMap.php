<?php

namespace Cinch\Project;

use Exception;

class EnvironmentMap
{
    private string $default;

    /** @var Environment[] */
    private array $environments = [];

    /**
     * @param Environment[] $environments
     * @throws Exception
     */
    public function __construct(string $default, array $environments)
    {
        foreach ($environments as $name => $e)
            $this->add($name, $e);
        $this->setDefault($default);
    }

    public function normalize(): array
    {
        $environments = ['default' => $this->default];

        foreach ($this->environments as $name => $e)
            $environments[$name] = $e->normalize();

        return $environments;
    }

    /**
     * @throws Exception
     */
    public function add(string $name, Environment $env): self
    {
        if ($this->has($name))
            throw new Exception("'$name' already exists");

        $this->environments[$name] = $env;
        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->environments[$name]);
    }

    public function get(string $name): Environment|null
    {
        return $this->environments[$name] ?? null;
    }

    public function getDefault(): Environment
    {
        return $this->get($this->default);
    }

    public function getDefaultName(): string
    {
        return $this->default;
    }

    /**
     * @throws Exception
     */
    public function setDefault(string $name): self
    {
        if (isset($this->name) && $this->default == $name)
            return $this;

        if (!$this->has($name))
            throw new Exception("'$name' does not exist, cannot be default");

        $this->default = $name;
        return $this;
    }

    public function all(): array
    {
        return $this->environments;
    }
}