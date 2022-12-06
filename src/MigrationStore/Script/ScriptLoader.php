<?php

namespace Cinch\MigrationStore\Script;

use Cinch\MigrationStore\Adapter\File;
use Cinch\MigrationStore\Adapter\LocalFile;
use Cinch\Common\Location;
use Cinch\Component\Assert\AssertException;
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
        if ($file->isSql())
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
        return $this->sqlScriptParser->parse($sql);
    }

    private function requireFile(LocalFile $file): Script
    {
        return $this->assertScript(require $file->getAbsolutePath(), $file->getLocation());
    }

    private function evalFile(File $file): Script
    {
        return $this->assertScript(eval('?>' . $file->getContents()), $file->getLocation());
    }

    private function assertScript(mixed $script, Location $location): Script
    {
        if (($script instanceof CanMigrate || $script instanceof CanRollback) && $script instanceof Script)
            return $script;

        throw new AssertException("$location must be a " . Script::class .
            " that implements " . CanMigrate::class . ' and/or ' . CanRollback::class);
    }
}