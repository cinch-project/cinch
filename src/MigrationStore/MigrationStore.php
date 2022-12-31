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
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment as Twig;

class MigrationStore
{
    const CONFIG_FILE = 'store.yml';
    /** (local filesystem only) follow symbolic links - yaml 'follow_links: true' */
    const FOLLOW_LINKS = 0x1000;
    private const PARSE_FLAGS = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP;

    /** @var Directory[] */
    private array|null $directories = null;
    private bool $populatedDirectories = false;
    private bool $followLinks = false;


    public function __construct(
        private readonly Adapter $adapter,
        private readonly ScriptLoader $scriptLoader,
        private readonly Twig $twig,
        private readonly LoggerInterface $logger,
        private readonly string $resourceDir)
    {
    }

    /**
     * @param StorePath $path
     * @return Migration
     * @throws Exception
     */
    public function get(StorePath $path): Migration
    {
        if (($dir = $this->getDirectoryFor($path)) === null)
            throw new DirectoryNotFoundException("cannot find directory for $path");
        return $dir->get($path);
    }

    /** Gets all migrations within store in directory migrate order.
     * @return Migration[]
     * @throws Exception
     */
    public function all(): array
    {
        $migrations = [];
        $this->populateDirectories();

        foreach ($this->getDirectories() as $dir)
            array_push($migrations, ...$dir->all());

        $this->logger->debug('found ' . count($migrations) . ' migration scripts');
        return $migrations;
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

    /**
     * @throws Exception
     */
    public function createConfig(): bool
    {
        try {
            $this->getDirectories();
            $created = false;
        }
        catch (FileNotFoundException) {
            $this->adapter->addFile(
                self::CONFIG_FILE,
                slurp(Path::join($this->resourceDir, self::CONFIG_FILE)),
                'created ' . self::CONFIG_FILE
            );

            $created = true;
        }

        return $created;
    }

    /** Deletes the store file, not the directory.
     * @return void
     */
    public function deleteConfig(): void
    {
        $this->adapter->deleteFile(self::CONFIG_FILE, 'deleted ' . self::CONFIG_FILE);
    }

    /**
     * @throws Exception
     */
    private function populateDirectories(): void
    {
        if ($this->populatedDirectories)
            return;

        /* to support remote git providers, all migrations for a store are recursively fetched: git tree.
         * This is a performance win (due to API overhead) and avoids requests per minute/hour rate limits.
         */
        if (!($directories = $this->getDirectories()))
            return;

        $this->populatedDirectories = true;
        $flags = $this->followLinks ? self::FOLLOW_LINKS : 0;

        if (!($files = $this->adapter->getFiles($flags))) {
            $this->logger->warning("migration store does not contain any migration scripts");
            return;
        }

        /* Since we have an unsorted list of all files, we need to match them to a directory. This finds the
         * deepest matching base-dir: /a/b/c.php should be added to /a/b rather than /a (see getDirectoryFor).
         */
        while ($file = array_shift($files))
            if ($dir = $this->getDirectoryFor($file->getPath()))
                $dir->add($file);

        /* migrate sort order: based on config options */
        foreach ($directories as $dir)
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
        $this->followLinks = Assert::ifPropSet($store, 'follow_links', false, "store.follow_links")
            ->bool()->value();

        $directories = [];
        $docPath = 'store.directories';

        foreach (Assert::arrayProp($store, 'directories', $docPath) as $i => $dir) {
            $subPath = "{$docPath}[$i]";
            $dirPath = Assert::thatProp($dir, 'path', "$subPath.path")->string()->notEmpty()->value();
            $directories[] = new Directory(
                $this->adapter,
                $this->scriptLoader,
                $dirPath,
                [...$variables, ...$this->parseVariables($dir, "$subPath.variables")],
                Assert::array($dir->exclude ?? [], "$subPath.exclude"),
                $this->parseSortPolicy($dir, "$subPath.sort"),
                $this->parseFlags($dir, $subPath)
            );
        }

        if ($directories)
            $this->logger->debug(self::CONFIG_FILE . ': found ' . count($directories) . ' directories');
        else
            $this->logger->warning(self::CONFIG_FILE . " has no directories configured");

        return $this->directories = $directories;
    }

    private function parseVariables(object $obj, string $docPath): array
    {
        $v = Assert::ifProp($obj, 'variables', (object) [], $docPath)->object()->value();
        return arrayify($v);
    }

    private function parseSortPolicy(object $dir, string $docPath): SortPolicy
    {
        $default = SortPolicy::NATURAL->value;
        return SortPolicy::from(Assert::ifPropSet($dir, 'sort', $default, $docPath)->string()->value());
    }

    private function parseFlags(object $dir, string $docPath): int
    {
        $flags = 0;

        if (Assert::ifPropSet($dir, 'recursive', false, "$docPath.recursive")->bool()->value())
            $flags |= Directory::RECURSIVE;

        if (Assert::ifPropSet($dir, 'error_if_empty', false, "$docPath.error_if_empty")->bool()->value())
            $flags |= Directory::ERROR_IF_EMPTY;

        if (Assert::ifPropSet($dir, 'environment', false, "$docPath.environment")->bool()->value())
            $flags |= Directory::ENVIRONMENT;

        return $flags;
    }
}