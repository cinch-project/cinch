<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\MigratePolicy;
use Cinch\Common\StorePath;
use Cinch\Component\Assert\Assert;
use Cinch\MigrationStore\Script\ScriptLoader;
use DateTimeInterface;
use Exception;
use Generator;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment as Twig;

class MigrationStore
{
    const FILENAME = 'store.yml';
    private const PARSE_FLAGS = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP;

    /** @var Directory[] */
    private array|null $directories = null;

    /* only used to know if we created store.yml as part of create-project or add-env. If something fails
     * during those commands, we need to delete the store.yml just created.
     */
    private bool|null $createdStoreConfig = null;

    public function __construct(
        private readonly Adapter $adapter,
        private readonly ScriptLoader $scriptLoader,
        private readonly Twig $twig,
        private readonly string $resourceDir)
    {
    }

    /**
     * @throws Exception
     */
    public function createConfig(): void
    {
        if (!$this->exists()) {
            $this->adapter->addFile(
                self::FILENAME,
                slurp(Path::join($this->resourceDir, self::FILENAME)),
                'created ' . self::FILENAME
            );

            $this->createdStoreConfig = true;
        }
        else {
            $this->createdStoreConfig = false;
        }
    }

    /** Deletes the store file, not the directory.
     * @return void
     */
    public function deleteConfig(): void
    {
        if ($this->createdStoreConfig === null || $this->createdStoreConfig)
            $this->adapter->deleteFile(self::FILENAME, 'deleted ' . self::FILENAME);
    }

    /**
     * @param StorePath $path
     * @return Migration
     * @throws Exception
     */
    public function get(StorePath $path): Migration
    {
        return $this->getDirectoryFor($path)->getMigration($path);
    }

    /**
     * @throws Exception
     */
    public function add(StorePath $path, MigratePolicy $migratePolicy, Author $author,
        DateTimeInterface $authoredAt, Description $description, Labels $labels): void
    {
        $content = $this->twig->render($path->isSql() ? 'sql.twig' : 'php.twig', [
            'migrate_policy' => $migratePolicy->value,
            'author' => $author->value,
            'authored_at' => $authoredAt->format('Y-m-d H:i:sP'),
            'description' => $description->value,
            'labels' => $labels->all()
        ]);

        $this->adapter->addFile($path->value, $content, 'add migration request');
    }

    public function remove(StorePath $path): void
    {
        $this->adapter->deleteFile($path->value, 'remove migration request');
    }

    /** Iterates through all migrations in directory migrate order.
     * @return Generator<Migration>
     * @throws Exception
     */
    public function iterate(): Generator
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
    private function getDirectoryFor(StorePath $storePath): Directory
    {
        $dir = null;
        $path = dirname($storePath->value) . '/'; // remove filename, append '/' to avoid false positives: /a, /ab

        /* find the deepest directory that is a base-path of store-path */
        foreach ($this->getDirectories() as $d) {
            if (mb_strpos($path, rtrim($d->path, '/') . '/', encoding: 'UTF-8') === 0) {
                if (!$dir || strlen($d->path) > strlen($dir->path))
                    $dir = $d;
            }
        }

        if ($dir)
            return $dir;

        throw new DirectoryNotFoundException("Cannot find directory for \"$storePath\"");
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

        $contents = $this->adapter->getContents(self::FILENAME);
        $store = Yaml::parse($contents, self::PARSE_FLAGS);
        $variables = $this->parseVariables($store, 'store.variables'); // top-level (global) variables

        $directories = [];
        $docPath = 'store.directories';

        foreach (Assert::arrayProp($store, 'directories', $docPath) as $i => $dir) {
            $subPath = "{$docPath}[$i]";
            $dirPath = Assert::thatProp($dir, 'path', "$subPath.path")->string()->notEmpty()->value();
            $directories[$dirPath] = new Directory(
                $this->adapter,
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