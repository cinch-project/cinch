<?php

namespace Cinch\MigrationStore;

use Cinch\Common\Checksum;
use Cinch\Common\Location;
use Cinch\MigrationStore\Script\Script;

class XXH128MigrationFactory implements MigrationFactory
{
    public function create(Location $location, Checksum $checksum, Script $script): Migration
    {
        $id = new MigrationId(hash('xxh128', $location->value));
        return new Migration($id, $location, $checksum, $script);
    }
}