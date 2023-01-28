<?php

namespace Cinch\Hook;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;

class Hook
{
    public const DEFAULT_TIMEOUT = 5;

    /**
     * @param Action $action
     * @param Event[] $events must contain at least one event
     * @param int $timeout
     * @param bool $abortOnError
     * @param array $arguments
     * @param array $headers
     */
    public function __construct(
        public readonly Action $action,
        public readonly array $events,
        public readonly int $timeout,
        public readonly bool $abortOnError,
        public readonly array $arguments,
        public readonly array $headers)
    {
        Assert::notEmpty($this->events, 'events');
        Assert::greaterThanEqualTo($this->timeout, 0, 'hook.timeout');
        $this->assertArguments();
        $this->assertHeaders();
    }

    public function snapshot(): array
    {
        return [
            'action' => (string) $this->action,
            'events' => array_map(fn ($e) => $e->value, $this->events),
            'timeout' => $this->timeout,
            'abort_on_error' => $this->abortOnError,
            'arguments' => (object) $this->arguments
        ];
    }

    private function assertArguments(): void
    {
        foreach ($this->arguments as $i => $a)
            if (!(is_string($a) || is_int($a) || is_float($a)))
                throw new AssertException(sprintf("hook.arguments[%d]: value must be string|int|float, " .
                    "found '%s'", $i, get_debug_type($a)));
    }

    private function assertHeaders(): void
    {
        foreach ($this->headers as $name => $value) {
            Assert::that($name, 'headers')->string()->notEmpty();
            if (!(is_string($value) || is_int($value) || is_float($value)))
                throw new AssertException(sprintf("hook.headers.%s: value must be string|int|float, " .
                    "found '%s'", $name, get_debug_type($value)));
        }
    }
}
