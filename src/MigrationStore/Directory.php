<?php

namespace Cinch\MigrationStore;

use Cinch\Component\Assert\AssertException;
use Cinch\MigrationStore\Adapter\File;
use Cinch\MigrationStore\Adapter\MigrationStoreAdapter;
use Cinch\MigrationStore\Script\ScriptLoader;
use Exception;
use Generator;

/** migration store directory object */
class Directory
{
    /** recursively search directory - yaml 'recursive: true' */
    const RECURSIVE = 0x01;
    /** raise error if directory is empty after filtering - yaml 'errorIfEmpty: true' */
    const ERROR_IF_EMPTY = 0x02;
    /** (local filesystem only) follow symbolic links - yaml 'followLinks: true' */
    const FOLLOW_LINKS = 0x04;
    /** replace environment variables when processing SQL scripts - yaml 'environment: true' */
    const ENVIRONMENT = 0x08;

    /**
     * @param string $path directory path "relative" to store directory
     * @throws Exception
     */
    public function __construct(
        private readonly MigrationStoreAdapter $storeAdapter,
        public readonly string $path,
        public readonly array $variables,
        public readonly array $exclude,
        public readonly SortPolicy $sortPolicy,
        public readonly int $flags)
    {
        $this->assertExclude();
    }

    /**
     * @param ScriptLoader $scriptLoader
     * @param MigrationFactory $migrationFactory
     * @return Generator<Migration>
     * @throws Exception
     */
    public function search(ScriptLoader $scriptLoader, MigrationFactory $migrationFactory): Generator
    {
        $files = $this->storeAdapter->search($this);

        if (($this->flags & self::ERROR_IF_EMPTY) && !$files)
            throw new Exception("migration store directory '$this->path' is empty");

        $files = $this->sort($files);

        while ($file = array_shift($files)) {
            $script = $scriptLoader->load($file, $this->variables, $this->flags & self::ENVIRONMENT);
            yield $migrationFactory->create($file->getLocation(), $file->getChecksum(), $script);
        }

        unset($files);
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
     * @param File[] $files
     * @return File[]
     */
    private function sort(array $files): array
    {
        $root = FileNode::root();

        /* build tree using path components as nodes */
        foreach ($files as $file) {
            $components = explode('/', $file->getLocation()->value);
            $filename = array_pop($components);

            for ($node = $root; $name = array_shift($components);)
                $node = $node->addChild($name);

            $node->addChild($filename, $file); // last component -- leaf node
        }

        /* sort tree recursively. toFiles returns all leaf nodes in depth-first order. */
        return $root->sort($this->sortPolicy)->toFiles();
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

