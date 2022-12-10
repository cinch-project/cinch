<?php

namespace Cinch\History;

use Cinch\Component\Assert\Assert;
use Exception;
use JsonSerializable;
use Symfony\Component\Filesystem\Path;

class DeploymentError implements JsonSerializable
{
    public static function fromException(Exception $e): static
    {
        /* get source root, which is 2 dirs up: "../.." */
        $base = dirname(__DIR__, 2);
        $trace = preg_split('~\R~', $e->getTraceAsString(), flags: PREG_SPLIT_NO_EMPTY);

        /* replace each trace path with a relative path */
        foreach ($trace as &$t)
            $t = preg_replace_callback('~^#\d+ (/.+)\(\d+\)~', fn($m) => Path::makeRelative($m[1], $base), $t);

        return new static(
            $e->getMessage() ?: '[error missing message]',
            get_class($e),
            Path::makeRelative($e->getFile(), $base),
            $e->getLine(),
            $trace
        );
    }

    /**
     * @param string $message error message, required
     * @param string $exception exception class
     * @param string $file file where exception was thrown
     * @param int $line line number where exception was thrown
     * @param string[] $trace stack trace as an array of strings
     */
    public function __construct(
        public readonly string $message,
        public readonly string $exception = '',
        public readonly string $file = '',
        public readonly int $line = 0,
        public readonly array $trace = [])
    {
        Assert::notEmpty($this->message, 'message');
    }

    public function jsonSerialize(): array
    {
        return (array) $this;
    }
}