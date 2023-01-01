<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;

class LocalFile extends File
{
    public function __construct(Adapter $adapter, private readonly string $absolutePath, StorePath $path)
    {
        parent::__construct($adapter, $path, Checksum::fromData(slurp($this->absolutePath)));
    }

    public function getAbsolutePath(): string
    {
        return $this->absolutePath;
    }
}