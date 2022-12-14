<?php

namespace Cinch\Project;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use Exception;

class Hook
{
    const DEFAULT_TIMEOUT = 5;

    /**
     * @throws Exception
     */
    public function __construct(
        public readonly HookScript $script,
        public readonly HookEvent $event,
        public readonly int $timeout,
        public readonly bool $rollback,
        public readonly array $arguments)
    {
        Assert::greaterThanEqualTo($this->timeout, 0, 'hook.timeout');
        $this->assertArguments();
    }

    public function snapshot(): array
    {
        return [
            'script' => $this->script->value,
            'event' => $this->event->value,
            'timeout' => $this->timeout,
            'rollback' => $this->rollback,
            'arguments' => (object) $this->arguments
        ];
    }

    private function assertArguments(): void
    {
        foreach ($this->arguments as $i => $a)
            if (!(is_string($a) || is_int($a) || is_float($a)))
                throw new AssertException(sprintf("hook.arguments[$i]: value must be string|int|float, " .
                    "found '%s'", get_debug_type($a)));
    }
}