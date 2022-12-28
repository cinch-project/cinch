<?php

namespace Cinch\MigrationStore;

use Cinch\Common\StorePath;
use Cinch\Component\Assert\AssertException;
use Cinch\MigrationStore\Script\ScriptLoader;
use Exception;

/** migration store directory object
 */
class Directory
{
    /** recursively search directory - yaml 'recursive: true' */
    const RECURSIVE = 0x01;
    /** raise error if directory is empty after filtering - yaml 'error_if_empty: true' */
    const ERROR_IF_EMPTY = 0x02;
    /** replace environment variables when processing SQL scripts - yaml 'environment: true' */
    const ENVIRONMENT = 0x04;

    /** @var Migration[] */
    private array $migrations = [];

    private readonly int $depth;

    /**
     * @param string $path directory path "relative" to store directory
     * @throws Exception
     */
    public function __construct(
        private readonly Adapter $adapter,
        private readonly ScriptLoader $scriptLoader,
        private readonly string $path,
        private readonly array $variables,
        private readonly array $exclude,
        private readonly SortPolicy $sortPolicy,
        private readonly int $flags)
    {
        $this->assertExclude();
        $this->depth = substr_count($this->path, '/');
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function add(File $file): void
    {
        $path = $file->getPath()->value;

        /* exclude this file? */
        foreach ($this->exclude as $pattern)
            if (preg_match($pattern, $path))
                return;

        /* ignore files with depths > dir when not recursive */
        if (!($this->flags & self::RECURSIVE) && substr_count($path, '/') > $this->depth)
            return;

        $this->migrations[] = new Migration($file, $this->scriptLoader, $this->variables, $this->flags);
    }

    public function all(): array
    {
        return $this->migrations;
    }

    /**
     * Example of sort (execution) order:
     *
     *     a/b/x.sql <- dirs first
     *     a/c/y.sql
     *     a/a.sql   <- a comes before b and c but files come after dirs
     *     a/b.sql   <- files sorted separately
     *     a.sql
     *
     * @return self
     * @throws Exception
     */
    public function sort(): self
    {
        if (($this->flags & self::ERROR_IF_EMPTY) && !$this->migrations)
            throw new Exception("$this->path is empty");

        $root = MigrationNode::root();

        /* build tree using path components as nodes */
        foreach ($this->migrations as $migration) {
            $components = explode('/', $migration->getPath()->value);
            $filename = array_pop($components);

            for ($node = $root; $name = array_shift($components);)
                $node = $node->addChild($name);

            $node->addChild($filename, $migration); // last component -- leaf node
        }

        /* sort tree recursively. toMigrations returns all leaf nodes in depth-first order. */
        $this->migrations = $root->sort($this->sortPolicy)->toMigrations();

        return $this;
    }

    /**
     * @param StorePath $path
     * @return Migration
     * @throws Exception
     */
    public function get(StorePath $path): Migration
    {
        $file = $this->adapter->getFile($path->value);
        return new Migration($file, $this->scriptLoader, $this->variables, $this->flags);
    }

    /**
     * @throws Exception
     */
    private function assertExclude(): void
    {
        foreach ($this->exclude as $i => $e)
            if (!is_string($e))
                throw new AssertException("directory.exclude[$i] is not a string, found " . get_debug_type($e));
    }
}

