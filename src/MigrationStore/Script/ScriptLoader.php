<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\StorePath;
use Cinch\Component\Assert\AssertException;
use Cinch\MigrationStore\Adapter\File;
use Cinch\MigrationStore\Adapter\LocalFile;
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
    public function load(File $file, array $variables, bool $environment): Script
    {
        if ($file->getPath()->isSql())
            $script = $this->parseSql($file, $variables, $environment);
        else if ($file instanceof LocalFile)
            $script = $this->requireFile($file);
        else
            $script = $this->evalFile($file);

        (new ReflectionMethod($script, 'setVariables'))->invoke($script, $variables);
        return $script;
    }

    /**
     * @throws Exception
     */
    private function parseSql(File $file, array $variables, bool $environment): Script
    {
        if ($environment)
            $variables = [...$variables, ...getenv()];

        $sql = $this->twig->createTemplate($file->getContents())->render($variables);

        try {
            return $this->sqlScriptParser->parse($sql);
        }
        catch (AssertException $e) {
            throw new AssertException("{$file->getContents()}: {$e->getMessage()}", $e->getErrors());
        }
    }

    private function requireFile(LocalFile $file): Script
    {
        return $this->assertScript(require $file->getAbsolutePath(), $file->getPath());
    }

    private function evalFile(File $file): Script
    {
        return $this->assertScript(eval('?>' . $file->getContents()), $file->getPath());
    }

    private function assertScript(mixed $script, StorePath $path): Script
    {
        if ($script instanceof Script)
            return $script;

        throw new AssertException("$path must be a " . Script::class);
    }
}