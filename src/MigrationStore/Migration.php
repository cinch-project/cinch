<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\Location;
use Cinch\MigrationStore\Script\Script;

class Migration
{
    public function __construct(
        public readonly Location $location,
        public readonly Checksum $checksum,
        public readonly Script $script)
    {
    }
}