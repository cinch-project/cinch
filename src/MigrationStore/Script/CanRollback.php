<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Database\Session;

interface CanRollback
{
    public function rollback(Session $session): void;
}