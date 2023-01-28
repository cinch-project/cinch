<?php

namespace Cinch\Hook;

use Cinch\Component\Assert\Assert;
use Cinch\Component\Assert\AssertException;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Filesystem\Path;

class Action
{
    private readonly ActionType $type;
    private readonly string $path;
    private readonly array $variables;

    public function __construct(private readonly string $action, string $basePath = '')
    {
        $variables = [];
        $uri = new Uri($this->action);

        if (($scheme = strtolower($uri->getScheme())) === 'https')
            $scheme = 'http';

        $this->type = $scheme ? ActionType::from($scheme) : ActionType::SCRIPT; // scripts are schemeless URIs

        if ($this->type == ActionType::HTTP) {
            $path = $this->action; // variables sent as is in URL query params
        }
        else {
            $path = Assert::notEmpty($uri->getPath(), 'hook action path');

            if ($basePath)
                $path = Path::makeAbsolute($path, $basePath);

            if ($this->type == ActionType::SCRIPT)
                Assert::executable($path, 'hook script action');

            $variables = $this->assertVariables($uri);
        }

        $this->path = $path;
        $this->variables = $variables;
    }

    public function getType(): ActionType
    {
        return $this->type;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function equals(Action $action): bool
    {
        return $this->action == $action->action;
    }

    public function __toString(): string
    {
        return $this->action;
    }

    private function assertVariables(Uri $uri): array
    {
        $variables = [];
        parse_str($uri->getQuery(), $variables);

        foreach ($variables as $name => $value) {
            Assert::that($name, 'action variable name')->string()->regex('~^[a-zA-Z_]\w*$~');
            if (!(is_string($value) || is_int($value) || is_float($value)))
                throw new AssertException(sprintf("action.variable.%s: value must be string|int|float, " .
                    "found '%s'", $name, get_debug_type($value)));
        }

        return $variables;
    }
}
