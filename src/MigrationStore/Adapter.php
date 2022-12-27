<?php

namespace Cinch\MigrationStore;

use Exception;
use Symfony\Component\Filesystem\Path;

abstract class Adapter
{
    const FILE_PATTERN = '~^[^.].*\.(?:sql|php)$~i';

    public function __construct(protected readonly string $storeDir)
    {
    }

    /** Recursively gets all files from the store.
     * @param int $flags see adapter implementation for which flags are supported.
     * @return File[]
     * @throws Exception
     */
    public abstract function getFiles(int $flags = 0): array;

    /** Adds (commits) a file.
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
        return Path::join($this->storeDir, trim($path, '/'));
    }
}