<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\StorePath;
use Cinch\MigrationStore\Script\Script;

class Migration
{
    public function __construct(
        public readonly StorePath $path,
        public readonly Checksum $checksum,
        public readonly Script $script)
    {
    }
}