<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;

class LocalFile extends File
{
    public function __construct(private readonly string $absolutePath, StorePath $path)
    {
        $contents = slurp($this->absolutePath);
        parent::__construct($path, Checksum::fromData($contents), $contents);
    }

    public function getAbsolutePath(): string
    {
        return $this->absolutePath;
    }
}