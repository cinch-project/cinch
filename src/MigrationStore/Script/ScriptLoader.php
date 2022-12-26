<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\StorePath;
use Cinch\Component\Assert\AssertException;
use Cinch\MigrationStore\File;
use Cinch\MigrationStore\LocalFile;
use Exception;
use ReflectionMethod;
use Twig\Environment as Twig;

class ScriptLoader
{
    public function __construct(
        private readonly SqlScriptParser $sqlScriptParser,
        private readonly Twig $twig)
    {
    }

    /**
     * @throws Exception
     */
    public function load(File $file, string $contents, array $variables, bool $environment): Script
    {
        $path = $file->getPath();

        if ($path->isSql())
            $script = $this->parseSql($path, $contents, $variables, $environment);
        else if ($file instanceof LocalFile)
            $script = $this->requireFile($file);
        else
            $script = $this->evalFile($path, $contents);

        (new ReflectionMethod($script, 'setVariables'))->invoke($script, $variables);
        return $script;
    }

    /**
     * @throws Exception
     */
    private function parseSql(StorePath $path, string $contents, array $variables, bool $environment): Script
    {
        if ($environment)
            $variables = [...$variables, ...getenv()];

        $sql = $this->twig->createTemplate($contents)->render($variables);

        try {
            return $this->sqlScriptParser->parse($sql);
        }
        catch (AssertException $e) {
            throw new AssertException("$path: {$e->getMessage()}", $e->getErrors());
        }
    }

    private function requireFile(LocalFile $file): Script
    {
        return $this->assertScript(require $file->getAbsolutePath(), $file->getPath());
    }

    private function evalFile(StorePath $path, string $contents): Script
    {
        return $this->assertScript(eval('?>' . $contents), $path);
    }

    private function assertScript(mixed $script, StorePath $path): Script
    {
        if ($script instanceof Script)
            return $script;

        throw new AssertException("$path must be a type of " . Script::class);
    }
}