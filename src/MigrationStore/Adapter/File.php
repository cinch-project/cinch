<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;

abstract class File
{
    public function __construct(protected readonly StorePath $path)
    {
    }

    public function getPath(): StorePath
    {
        return $this->path;
    }

    public abstract function getChecksum(): Checksum;

    public abstract function getContents(): string;
}