<?php

namespace Cinch\Project;

use Cinch\Common\Environment;
use Exception;

class EnvironmentMap
{
    /**
     * @param string $default
     * @param Environment[] $map
     */
    public function __construct(
        private readonly string $default,
        private readonly array $map)
    {
    }

    public function normalize(): array
    {
        $environments = ['default' => $this->default];

        foreach ($this->map as $name => $e)
            $environments[$name] = $e->normalize();

        return $environments;
    }

    /**
     * @throws Exception
     */
    public function add(string $name, Environment $env): EnvironmentMap
    {
        if ($this->has($name))
            throw new Exception("cannot add environment '$name': already exists");

        $map = $this->map;
        $map[$name] = $env;

        return new self($this->default, $map);
    }

    /**
     * @throws Exception
     */
    public function remove(string $name): EnvironmentMap
    {
        if ($this->default == $name)
            throw new Exception("cannot remove environment '$name': currently the default");

        if (!$this->has($name))
            throw new Exception("cannot remove environment '$name': does not exist");

        $map = $this->map;
        unset($map[$name]);

        return new self($this->default, $map);
    }

    public function has(string $name): bool
    {
        return isset($this->map[$name]);
    }

    /**
     * @throws Exception
     */
    public function get(string $name): Environment
    {
        if (!$this->has($name))
            throw new Exception("environment '$name' does not exist");
        return $this->map[$name];
    }

    public function getDefault(): Environment
    {
        return $this->map[$this->default];
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
        if ($this->default == $name)
            return $this;

        if (!$this->has($name))
            throw new Exception("'$name' does not exist, cannot be default");

        return new self($name, $this->map);
    }

    /**
     * @return Environment[]
     */
    public function all(): array
    {
        return $this->map;
    }
}