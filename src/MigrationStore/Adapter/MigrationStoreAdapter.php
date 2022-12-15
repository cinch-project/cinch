<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\MigrationStore\Directory;
use Exception;
use Symfony\Component\Filesystem\Path as PathUtils;

abstract class MigrationStoreAdapter
{
    const FILENAME_PATTERN = '~\.(?:sql|php)$~i';

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
     */
    public abstract function addFile(string $path, string $content, string $message): void;

    public abstract function deleteFile(string $path, string $message): void;

    /**
     * @param string $path
     * @return File
     * @throws Exception
     */
    public abstract function getFile(string $path): File;

    /** Gets the contents of a file.
     * @throws Exception
     */
    abstract public function getContents(string $path): string;

    protected function resolvePath(string $path): string
    {
        return PathUtils::join($this->storeDir, trim($path, '/'));
    }
}