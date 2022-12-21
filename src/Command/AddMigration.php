<?php

namespace Cinch\Command;

use Cinch\Common\Author;
use Cinch\Common\Description;
use Cinch\Common\Labels;
use Cinch\Common\MigratePolicy;
use Cinch\Common\StorePath;
use Cinch\Project\ProjectId;
use DateTimeInterface;

class AddMigration
{
    public function __construct(
        public readonly ProjectId $projectId,
        public readonly StorePath $path,
        public readonly MigratePolicy $migratePolicy,
        public readonly Author $author,
        public readonly DateTimeInterface $authoredAt,
        public readonly Description $description,
        public readonly Labels $labels)
    {
    }
}