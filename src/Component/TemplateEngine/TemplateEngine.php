<?php

namespace Cinch\Component\TemplateEngine;

use Exception;
use Symfony\Component\Filesystem\Path;

/** Super simple and tiny template engine. Just does variable replacements. */
class TemplateEngine
{
    public function __construct(private readonly string $templateDir)
    {
    }

    public function renderTemplate(string $template, array $context = []): string
    {
        if (Path::isRelative($template))
            $template = Path::join($this->templateDir, $template);
        return $this->renderString(slurp($template), $context);
    }

    public function renderString(string $data, array $context = []): string
    {
        /* nothing to replace */
        if (!$context)
            return $data;

        /* replace ${variable} with context and/or env */
        return @preg_replace_callback('~\$\{([a-zA-Z_]\w*)}~S',
            static fn (array $m) => $context[$m[1]] ?? $m[0], $data);
    }
}
