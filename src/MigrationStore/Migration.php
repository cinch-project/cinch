<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\Script\Script;
use Exception;

class Migration
{
    private Script|null $script = null;

    public function __construct(private readonly Directory $directory, private readonly File $file)
    {
    }

    public function getPath(): StorePath
    {
        return $this->file->getPath();
    }

    public function getChecksum(): Checksum
    {
        return $this->file->getChecksum();
    }

    /**
     * @throws Exception
     */
    public function getScript(): Script
    {
        if ($this->script === null)
            $this->script = $this->directory->loadScript($this->file);
        return $this->script;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }
}