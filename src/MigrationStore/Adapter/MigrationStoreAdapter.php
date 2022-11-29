<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\MigrationStore\Directory;
use Cinch\MigrationStore\SortPolicy;
use Cinch\Component\Assert\Assert;
use Exception;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

abstract class MigrationStoreAdapter
{
    const FILENAME = 'store.yml';
    const FILENAME_PATTERN = '~\.(?:sql|php)$~i';
    private const PARSE_FLAGS = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP;

    public function __construct(protected readonly string $storeDir)
    {
    }

    /** Searches a directory for files: recursively or not.
     * @param Directory $dir
     * @return File[]
     * @throws Exception
     */
    public abstract function search(Directory $dir): array;

    /** Adds a file. This is an optional feature. For remote migration stores, cinch will require write access.
     * This is only used to create a default store.yml during create project or generate a template
     * migration script for add-script. Both can be done manually.
     * @param string $path
     * @param string $content
     * @param string $message
     * @return FileId
     */
    public abstract function addFile(string $path, string $content, string $message): FileId;

    public abstract function deleteFile(string $path, string $message, FileId $fileId): void;

    /** Gets the contents of a file.
     * @throws Exception
     */
    protected abstract function getContentsByPath(string $path): string;

    /** Gets the directories from the migration store.
     * @return Directory[]
     * @throws Exception
     */
    public function getDirectories(): array
    {
        $store = Yaml::parse($this->getContentsByPath(self::FILENAME), self::PARSE_FLAGS);
        $variables = $this->parseVariables($store, 'store.variables'); // top-level (global) variables

        $directories = [];
        $docPath = 'store.directories';

        foreach (Assert::arrayProp($store, 'directories', $docPath) as $i => $dir) {
            $subPath = "{$docPath}[$i]";
            $directories[] = new Directory(
                $this,
                Assert::thatProp($dir, 'path', "$subPath.path")->string()->notEmpty()->value(),
                [...$variables, ...$this->parseVariables($dir, "$subPath.variables")],
                Assert::array($dir->exclude ?? [], "$subPath.exclude"),
                $this->parseSortPolicy($dir, "$subPath.sort"),
                $this->parseFlags($dir, $subPath)
            );
        }

        return $directories;
    }

    protected function resolvePath(string $path): string
    {
        return Path::join($this->storeDir, trim($path, '/'));
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