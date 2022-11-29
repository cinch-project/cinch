<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Database\Session;

interface Committable
{
    public function commit(Session $session): void;
}