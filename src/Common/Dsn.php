<?php

namespace Cinch\Common;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use ReflectionObject;
use ReflectionProperty;
use Stringable;

abstract class Dsn
{
    /** connect timeout in seconds */
    const DEFAULT_CONNECT_TIMEOUT = 10;
    /** query/request timeout in milliseconds */
    const DEFAULT_TIMEOUT = 15000;

    public readonly string $driver;
    public readonly int $connectTimeout;
    public readonly int $timeout;
    public readonly string|null $sslca;
    public readonly string|null $sslcert;
    public readonly string|null $sslkey;

    /* parameters that should be hidden when requesting a 'secure' string representation.
     * These must be in snake_case, as they are the raw parameter names.
     */
    protected array $hidden = [];

    /**
     * @param string|array|object $dsn string key/value format, assoc array or object. In all cases, the keys are
     * expected to be in snake_case.
     */
    public function __construct(string|array|object $dsn)
    {
        $this->setParameters(match (gettype($dsn)) {
            'string' => $this->parseParameters($dsn),
            'object' => arrayify($dsn),
            'array' => $dsn
        });
    }

    protected function setParameters(array $params): void
    {
        $this->driver = Assert::thatKey($params, 'driver', 'dsn driver')->regex('~^[a-z\-]{1,32}$~')->value();
        $this->connectTimeout = Assert::ifKey($params, 'connect_timeout', self::DEFAULT_CONNECT_TIMEOUT, "$this->driver connect_timeout")->int()->greaterThan(0)->value();
        $this->timeout = Assert::ifKey($params, 'timeout', self::DEFAULT_TIMEOUT, "$this->driver timeout")->int()->greaterThan(0)->value();
        $this->sslca = isset($params['sslca']) ? Assert::file($params['sslca'], "$this->driver sslca") : null;
        $this->sslcert = isset($params['sslcert']) ? Assert::file($params['sslcert'], "$this->driver sslcert") : null;
        $this->sslkey = isset($params['sslkey']) ? Assert::file($params['sslkey'], "$this->driver sslkey") : null;
    }

    public function snapshot(): array
    {
        return $this->getParameters();
    }

    /** Gets a string representation of dsn.
     * @param bool $secure generate a secure version, which means hidden values are set to '****': like passwords,
     * tokens, secrets, etc.
     * @return string
     */
    public function toString(bool $secure = true): string
    {
        $dsn = '';

        foreach ($this->getParameters() as $k => $v)
            $dsn .= "$k=" . ($secure && in_array($k, $this->hidden) ? '****' : $this->escapeParameterValue($v)) . ' ';

        return substr($dsn, 0, -1);
    }

    /** Gets the "secure" string representation.
     * @return string
     * @see toString
     */
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
        /* example: "name=value name = value name='va\'lue'" */
        static $pattern = "~([a-zA-Z_]+)\s*=\s*('[^'\\\]*'|\S+)~";

        if (preg_match_all($pattern, $dsn, $matches, PREG_SET_ORDER) === false)
            throw new AssertException("DSN '$dsn' does not match $pattern");

        $params = [];

        foreach ($matches as $m)
            $params[$m[1]] = $this->unescapeParameterValue($m[2]);

        return $params;
    }

    private function escapeParameterValue(mixed $v): string
    {
        if ($v instanceof Stringable)
            $v = (string) $v;

        if (is_string($v) && strcspn($v, " \t") != strlen($v))
            $v = sprintf("'%s'", str_replace(["\\", "'"], ["\\\\", "\\'"], $v));

        return $v;
    }

    private function unescapeParameterValue(string $v): string
    {
        $quoted = strlen($v) > 1 && str_starts_with($v, "'") && str_ends_with($v, "'");
        return $quoted ? str_replace(["\\\\", "\\'"], ['\\', "'"], substr($v, 1, -1)) : $v;
    }
}