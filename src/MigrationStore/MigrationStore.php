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
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment as Twig;

class MigrationStore
{
    const CONFIG_FILE = 'store.yml';
    private const PARSE_FLAGS = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP;

    /** @var Directory[] */
    private array|null $directories = null;

    /* only used to know if we created store.yml as part of create-project or add-env. If something fails
     * during those commands, we need to delete the store.yml just created.
     */
    private bool $createdStoreConfig = false;

    private bool $populatedDirectories = false;

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
                self::CONFIG_FILE,
                slurp(Path::join($this->resourceDir, self::CONFIG_FILE)),
                'created ' . self::CONFIG_FILE
            );

            $this->createdStoreConfig = true;
        }
    }

    /** Deletes the store file, not the directory.
     * @return void
     */
    public function deleteConfig(): void
    {
        if ($this->createdStoreConfig)
            $this->adapter->deleteFile(self::CONFIG_FILE, 'deleted ' . self::CONFIG_FILE);
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
     * @return Migration[]
     * @throws Exception
     */
    public function all(): array
    {
        $migrations = [];
        $this->populateDirectories();

        foreach ($this->getDirectories() as $dir)
            array_push($migrations, ...$dir->all());

        return $migrations;
    }

    /**
     * @throws Exception
     */
    private function populateDirectories(): void
    {
        if ($this->populatedDirectories)
            return;

        $this->populatedDirectories = true;

        /* to support remote git providers, all migrations for a store are recursively fetched: git tree.
         * This is a performance win (due to API overhead) and avoids requests per minute/hour rate limits.
         * Although Local adapter doesn't require this, it behaves identically to keep things generic.
         */
        if (!$this->getDirectories() || !($files = $this->adapter->getFiles()))
            return;

        /* Since we have an unsorted list of all files, we need to match them to a directory. some may not be
         * ready to deploy, meaning their parent dirs are not listed within the store's config file. Some may
         * be 1+ levels deep, while the directory is marked non-recursive. These are simply ignored. This finds the
         * deepest matching base-dir: /a/b/c.php should be added to /a/b rather than /a (see getDirectoryFor).
         */
        while ($file = array_shift($files))
            if ($dir = $this->getDirectoryFor($file->getPath()))
                $dir->add($file);

        /* migrate sort order: based on config options */
        foreach ($this->getDirectories() as $dir)
            $dir->sort();
    }

    /**
     * @throws Exception
     */
    private function getDirectoryFor(StorePath $storePath): Directory|null
    {
        $candidate = null;
        $path = dirname($storePath->value) . '/'; // remove filename, append '/' to avoid false positives: /a, /ab

        /* find the deepest directory that is a base-path of store-path */
        foreach ($this->getDirectories() as $dir) {
            if (mb_stripos($path, rtrim($dir->getPath(), '/') . '/', encoding: 'UTF-8') === 0) {
                if (!$candidate || $dir->getDepth() > $candidate->getDepth())
                    $candidate = $dir;
            }
        }

        return $candidate;
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

        $contents = $this->adapter->getContents(self::CONFIG_FILE);
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