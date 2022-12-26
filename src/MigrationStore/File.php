<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;

class File
{
    public function __construct(
        private readonly StorePath $path,
        private readonly Checksum $checksum,
        private readonly string|null $contents = null)
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

    public function getContents(): string|null
    {
        return $this->contents;
    }
}