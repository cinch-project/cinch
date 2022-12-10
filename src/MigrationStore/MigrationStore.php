<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Location;
use Cinch\Component\Assert\Assert;
use Cinch\MigrationStore\Adapter\FileId;
use Cinch\MigrationStore\Adapter\MigrationStoreAdapter;
use Cinch\MigrationStore\Script\ScriptLoader;
use Exception;
use Generator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Yaml\Yaml;

class MigrationStore
{
    const FILENAME = 'store.yml';
    private const PARSE_FLAGS = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP;

    /** @var Directory[] */
    private array|null $directories = null;

    /* track ID for deletes (rollbacks). This is really designed for remote stores where an ID is needed for
     * deletes (avoids expensive REST calls). Not all adapters need this, in which case the ID is empty.
     */
    private FileId|null $storeId = null;

    public function __construct(
        private readonly MigrationStoreAdapter $storeAdapter,
        private readonly ScriptLoader $scriptLoader,
        private readonly string $resourceDir)
    {
    }

    /**
     * @throws Exception
     */
    public function create(): void
    {
        if (!$this->exists())
            $this->storeId = $this->storeAdapter->addFile(
                self::FILENAME,
                slurp(Path::join($this->resourceDir, self::FILENAME)),
                'created ' . self::FILENAME
            );
    }

    /** Deletes the store file, not the directory. Only deletes if created with MigrationStore::create().
     * @return void
     */
    public function delete(): void
    {
        /* only delete if cinch created it, existing store.yml files are never deleted. */
        if ($this->storeId !== null)
            $this->storeAdapter->deleteFile(
                self::FILENAME,
                'deleted ' . self::FILENAME,
                $this->storeId
            );
    }

    /**
     * @param Location $location
     * @return Migration
     * @throws Exception
     */
    public function getMigration(Location $location): Migration
    {
        return $this->getDirectoryFor($location)->getMigration($location);
    }

    /** Iterates through all migrations in directory migrate order.
     * @return Generator<Migration>
     * @throws Exception
     */
    public function iterateMigrations(): Generator
    {
        if (!$this->exists())
            throw new Exception(self::FILENAME . ' does not exist');

        foreach ($this->directories as $dir)
            foreach ($dir->search() as $migration)
                yield $migration;
    }

    /**
     * @throws Exception
     */
    private function getDirectoryFor(Location $location): Directory
    {
        $dir = null;
        $path = dirname($location->value) . '/'; // remove filename, append '/' to avoid false positives: /a, /ab

        /* find the deepest directory that is a base-path of location */
        foreach ($this->getDirectories() as $d) {
            if (mb_strpos($path, rtrim($d->path, '/') . '/', encoding: 'UTF-8') === 0) {
                if (!$dir || strlen($d->path) > strlen($dir->path))
                    $dir = $d;
            }
        }

        if ($dir)
            return $dir;

        throw new DirectoryNotFoundException("Cannot find directory for \"$path\"");
    }

    /**
     * @throws Exception
     */
    private function exists(): bool
    {
        try {
            $this->getDirectories();
            return true;
        }
        catch (FileNotFoundException) {
            return false;
        }
    }

    /** Gets the directories from the migration store.
     * @return Directory[]
     * @throws Exception
     */
    private function getDirectories(): array
    {
        if ($this->directories !== null)
            return $this->directories;

        $contents = $this->storeAdapter->getContents(self::FILENAME);
        $store = Yaml::parse($contents, self::PARSE_FLAGS);
        $variables = $this->parseVariables($store, 'store.variables'); // top-level (global) variables

        $directories = [];
        $docPath = 'store.directories';

        foreach (Assert::arrayProp($store, 'directories', $docPath) as $i => $dir) {
            $subPath = "{$docPath}[$i]";
            $dirPath = Assert::thatProp($dir, 'path', "$subPath.path")->string()->notEmpty()->value();
            $directories[$dirPath] = new Directory(
                $this->storeAdapter,
                $this->scriptLoader,
                $dirPath,
                [...$variables, ...$this->parseVariables($dir, "$subPath.variables")],
                Assert::array($dir->exclude ?? [], "$subPath.exclude"),
                $this->parseSortPolicy($dir, "$subPath.sort"),
                $this->parseFlags($dir, $subPath)
            );
        }

        return $directories;
    }

    private function parseVariables(object $obj, string $docPath): array
    {
        $v = Assert::ifProp($obj, 'variables', (object) [], $docPath)->object()->value();
        return objectToArray($v);
    }

    private function parseSortPolicy(object $dir, string $docPath): SortPolicy
    {
        $default = SortPolicy::NATURAL->value;
        $sort = Assert::ifPropSet($dir, 'sort', $default, $docPath)->string()->value();
        return SortPolicy::from($sort);
    }

    private function parseFlags(object $dir, string $docPath): int
    {
        $flags = 0;

        if (Assert::ifPropSet($dir, 'recursive', false, "$docPath.recursive")->bool()->value())
            $flags |= Directory::RECURSIVE;

        if (Assert::ifPropSet($dir, 'errorIfEmpty', false, "$docPath.errorIfEmpty")->bool()->value())
            $flags |= Directory::ERROR_IF_EMPTY;

        if (Assert::ifPropSet($dir, 'followLinks', false, "$docPath.followLinks")->bool()->value())
            $flags |= Directory::FOLLOW_LINKS;

        if (Assert::ifPropSet($dir, 'environment', false, "$docPath.environment")->bool()->value())
            $flags |= Directory::ENVIRONMENT;

        return $flags;
    }
}