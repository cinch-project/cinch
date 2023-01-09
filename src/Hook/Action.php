<?php

namespace Cinch\Hook;

use Cinch\Component\Assert\Assert;
use GuzzleHttp\Psr7\Uri;
use Symfony\Component\Filesystem\Path;

class Action
{
    private readonly ActionType $type;
    private readonly string $path;
    private readonly array $variables;

    public function __construct(private readonly string $action, string $basePath = '')
    {
        $uri = new Uri($this->action);
        $scheme = strtolower($uri->getScheme() ?: 'script');

        if ($scheme == 'http') {
            $this->variables = [];
            $this->path = $this->action;
        }
        else {
            $path = Assert::notEmpty($uri->getPath(), 'hook.action');
            if ($basePath)
                $path = Path::makeAbsolute($path, $basePath);
            $this->path = Assert::executable($path, 'hook.action');

            if ($scheme == 'handler')
                $scheme = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            $q = [];
            parse_str($uri->getQuery(), $q);
            $this->variables = $q;
        }

        $this->type = ActionType::from($scheme);
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
}