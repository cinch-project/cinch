<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use ReflectionObject;
use ReflectionProperty;

abstract class Dsn
{
    const DEFAULT_CONNECT_TIMEOUT = 10;
    const DEFAULT_TIMEOUT = 15000;

    public readonly string $driver;
    public readonly int $connectTimeout;
    public readonly int $timeout;
    public readonly string|null $sslca;
    public readonly string|null $sslcert;
    public readonly string|null $sslkey;

    /* parameters that should be hidden when requesting a 'secure' string representation: snake case */
    protected array $hidden = [];

    public function __construct(string|array|object $dsn)
    {
        if (is_object($dsn))
            $dsn = arrayify($dsn);
        else if (is_string($dsn))
            $dsn = $this->parseParameters($dsn);
        $this->setParameters($dsn);
    }

    protected function setParameters(array $params): void
    {
        $k = 'connect_timeout';
        $this->driver = Assert::thatKey($params, 'driver', 'driver')->notEmpty()->value();
        $this->connectTimeout = isset($params[$k]) ? Assert::int($params[$k], $k) : self::DEFAULT_CONNECT_TIMEOUT;
        $this->timeout = isset($params['timeout']) ? Assert::int($params['timeout'], 'timeout') : self::DEFAULT_TIMEOUT;
        $this->sslca = isset($params['sslca']) ? Assert::file($params['sslca'], 'sslca') : null;
        $this->sslcert = isset($params['sslcert']) ? Assert::file($params['sslcert'], 'sslcert') : null;
        $this->sslkey = isset($params['sslkey']) ? Assert::file($params['sslkey'], 'sslkey') : null;
    }

    public function snapshot(): array
    {
        return $this->getParameters();
    }

    /** Gets a string representation of dsn.
     * @param bool $secure generate a secure version, which means hidden values are set to '****'.
     * @return string
     */
    public function toString(bool $secure = true): string
    {
        $dsn = '';

        foreach ($this->getParameters() as $name => $value) {
            if ($secure && in_array($name, $this->hidden))
                $value = '****';
            else if (strcspn($value, " \t") != strlen($value)) // space|tab requires quoting value
                $value = "'" . str_replace(["\\", "'"], ["\\\\", "\'"], $value) . "'";

            $dsn .= "$name=$value ";
        }

        return substr($dsn, 0, -1);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /** Gets all non-null public readonly properties.
     * @return array keys are converted to snake_case
     */
    protected function getParameters(): array
    {
        $params = [];

        foreach ((new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $p)
            if ($p->isReadOnly() && ($value = $p->getValue($this)) !== null)
                $params[snakecase($p->getName())] = $value;

        return $params;
    }

    private function parseParameters(string $dsn): array
    {
        static $pattern = "~([a-zA-Z_]+)\s*=\s*('[^'\\\]*'|\S+)~";

        if (preg_match_all($pattern, $dsn, $matches, PREG_SET_ORDER) === false)
            throw new AssertException("DSN '$dsn' does not match $pattern");

        $params = [];
        foreach ($matches as $p)
            $params[$p[1]] = str_replace(["\\\\", "\\'"], ['\\', "'"], trim($p[2], "'"));

        return $params;
    }
}