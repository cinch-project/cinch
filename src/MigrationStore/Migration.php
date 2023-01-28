<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\Script\Script;
use Cinch\MigrationStore\Script\ScriptLoader;
use Exception;

class Migration
{
    private Script|null $script = null;

    public function __construct(
        private readonly File $file,
        private readonly ScriptLoader $scriptLoader,
        private readonly array $variables,
        private readonly int $flags)
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
            $this->script = $this->scriptLoader->load($this->file, $this->variables, $this->flags);
        return $this->script;
    }

    public function __toString(): string
    {
        return $this->getPath();
    }
}
