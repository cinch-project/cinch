<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;
use Exception;

class File
{
    public function __construct(
        private readonly Adapter $adapter,
        private readonly StorePath $path,
        private readonly Checksum $checksum,
        private string|null $contents = null)
    {
    }

    public function getPath(): StorePath
    {
        return $this->path;
    }

    public function getChecksum(): Checksum
    {
        return $this->checksum;
    }

    /**
     * @throws Exception
     */
    public function getContents(): string|null
    {
        if ($this->contents === null)
            $this->contents = $this->adapter->getContents($this->path->value);
        return $this->contents;
    }
}
