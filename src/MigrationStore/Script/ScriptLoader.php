<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Common\StorePath;
use Cinch\Component\Assert\AssertException;
use Cinch\MigrationStore\Directory;
use Cinch\MigrationStore\File;
use Cinch\MigrationStore\LocalFile;
use Exception;
use RuntimeException;
use Throwable;
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
    public function load(File $file, array $variables, int $flags): Script
    {
        $path = $file->getPath();
        $environment = (bool) ($flags & Directory::ENVIRONMENT);

        if ($path->isSql())
            $script = $this->parseSql($path, $file->getContents(), $variables, $environment);
        else if ($file instanceof LocalFile)
            $script = $this->requireFile($file); // does 'require', don't call getContents()
        else
            $script = $this->evalFile($path, $file->getContents());

        $script->setVariables($variables);
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
        try {
            return $this->assertScript(require $file->getAbsolutePath(), $file->getPath());
        }
        catch (Throwable $e) {
            throw new RuntimeException(sprintf('require(%s) failed - %s in %s:%d', $file->getPath(),
                $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }

    private function evalFile(StorePath $path, string $contents): Script
    {
        try {
            return $this->assertScript(eval('?>' . $contents), $path);
        }
        catch (Throwable $e) {
            throw new RuntimeException(sprintf('eval(%s) failed - %s in %s:%d', $path,
                $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }

    private function assertScript(mixed $script, StorePath $path): Script
    {
        if ($script instanceof Script)
            return $script;

        throw new AssertException("$path must be a type of " . Script::class);
    }
}
