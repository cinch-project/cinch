<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\Location;
use Cinch\MigrationStore\Script\Script;

class CinchMigrationFactory implements MigrationFactory
{
    public function create(Location $location, Checksum $checksum, Script $script): Migration
    {
        return new Migration($location, $checksum, $script);
    }
}