<?php

namespace Cinch\MigrationStore\Script;

use Cinch\Database\Session;

interface Revertable
{
    public function revert(Session $session): void;
}