<?php

namespace Cinch\MigrationStore\Adapter;

use Cinch\Common\Checksum;
use Cinch\Common\Location;

class LocalFile extends File
{
    private Checksum|null $checksum = null;

    public function __construct(private readonly string $absolutePath, Location $location)
    {
        parent::__construct($location);
    }

    public function getAbsolutePath(): string
    {
        return $this->absolutePath;
    }

    public function getContents(): string
    {
        $contents = slurp($this->absolutePath);
        $this->checksum = Checksum::fromData($contents);
        return $contents;
    }

    public function getChecksum(): Checksum
    {
        if ($this->checksum === null)
            $this->getContents();
        return $this->checksum;
    }
}