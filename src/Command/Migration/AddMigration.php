<?php

namespace Cinch\Command\Migration;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Dsn;
use Cinch\Common\Location;
use Cinch\Common\MigratePolicy;
use DateTimeInterface;

class AddMigration
{
    public function __construct(
        public readonly Dsn $migrationStoreDsn,
        public readonly Location $location,
        public readonly MigratePolicy $migratePolicy,
        public readonly Author $author,
        public readonly DateTimeInterface $authoredAt,
        public readonly Description $description)
    {
    }
}