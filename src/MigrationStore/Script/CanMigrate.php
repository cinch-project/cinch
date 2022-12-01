<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Database\Session;

interface CanMigrate
{
    public function migrate(Session $session): void;
}