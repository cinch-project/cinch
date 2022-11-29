<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;
use GuzzleHttp\Psr7\Uri;

class Dsn extends Uri
{
    private const SCHEMES = ['file', 'mysql', 'pgsql', 'mssql', 'sqlite', 'github', 'gitlab', 'azure'];

    private readonly string $user;
    private readonly string $password;
    private readonly array $options;

    public function __construct(string $dsn)
    {
        Assert::notEmpty($dsn, 'dsn');

        $uri = new Uri($dsn);
        if (!$uri->getScheme())
            $uri = $uri->withScheme('file');
        else
            Assert::in($uri->getScheme(), self::SCHEMES, 'DSN scheme');

        parent::__construct((string) $uri);

        $q = [];
        parse_str($this->getQuery(), $q);
        $this->options = $q;

        $parts = explode(':', $this->getUserInfo());
        $this->user = array_shift($parts) ?? '';
        $this->password = array_shift($parts) ?? '';
    }

    public function getUser(string $default = ''): string
    {
        return $this->user ?: $default;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->hasOption($name) ? $this->options[$name] : $default;
    }

    public function getFile(string $name, mixed $default = null): mixed
    {
        return $this->hasOption($name) ? Assert::exists($this->getOption($name), $name) : $default;
    }

    public function getConnectTimeout(): int
    {
        return $this->getIntOption('connect_timeout', 10);
    }

    public function getTimeout(): int
    {
        return $this->getIntOption('timeout', 10000);
    }

    public function getIntOption(string $name, int $default): int
    {
        if (!$this->hasOption($name))
            return $default;
        return Assert::digit($this->options[$name], "option '$name'");
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    public function equals(Dsn $dsn): bool
    {
        return (string) $this == (string) $dsn;
    }
}